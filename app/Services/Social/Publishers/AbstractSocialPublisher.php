<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\Social\Contracts\SocialPublisherInterface;
use Illuminate\Support\Facades\Http;

abstract class AbstractSocialPublisher implements SocialPublisherInterface
{
    abstract public function getPlatformCode(): string;

    /**
     * Retrieve Partner App Client ID from DB Settings or config/services.php
     */
    protected function getClientId(): ?string
    {
        $code = $this->getPlatformCode();
        return Setting::get("partner_{$code}_client_id") ?: config("services.{$code}.client_id");
    }

    /**
     * Retrieve Partner App Client Secret from DB Settings or config/services.php
     */
    protected function getClientSecret(): ?string
    {
        $code = $this->getPlatformCode();
        return Setting::get("partner_{$code}_client_secret") ?: config("services.{$code}.client_secret");
    }

    /**
     * Determine whether the token is a sandbox/simulated token or test environment
     */
    protected function isSandboxToken(string $token): bool
    {
        if (config('app.env') === 'testing') {
            return true;
        }

        return str_starts_with($token, 'mock_') || str_starts_with($token, 'partner_oauth_');
    }

    /**
     * Get valid access token for the account, refreshing if expired
     */
    protected function getAccessToken(SocialAccount $account): string
    {
        $credential = $account->credential;

        if (! $credential || empty($credential->access_token)) {
            throw new \RuntimeException("{$this->getPlatformCode()} hesabı için yetkilendirme anahtarı (access token) bulunamadı.");
        }

        if ($credential->isExpired()) {
            $refreshed = $this->refreshToken($account);
            if (! $refreshed) {
                throw new \RuntimeException("{$this->getPlatformCode()} token süresi dolmuş ve yenilenemedi. Yeniden bağlanması gerekiyor.");
            }
            $credential->refresh();
        }

        return $credential->access_token;
    }

    public function validateMedia(Video $video): array
    {
        return [
            'valid' => true,
            'errors' => [],
        ];
    }

    public function validateConnection(SocialAccount $account): bool
    {
        return $account->status === 'active' && $account->credential()->exists();
    }

    public function refreshToken(SocialAccount $account): bool
    {
        $credential = $account->credential;
        if (! $credential) {
            return false;
        }

        if ($this->isSandboxToken($credential->access_token)) {
            $credential->update([
                'token_expires_at' => now()->addMonths(3),
            ]);
            return true;
        }

        return true;
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        return [
            'views' => 0,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
        ];
    }

    public function getRateLimitStatus(SocialAccount $account): array
    {
        $limit = $account->platform?->daily_post_limit ?? 25;
        return [
            'remaining_today' => $limit,
            'is_limited' => false,
        ];
    }

    /**
     * Format captions with hashtags and optional product link (with automatic deduplication)
     */
    protected function buildFullCaption(ScheduledPostTarget $target): string
    {
        $caption = trim((string) ($target->caption ?: ''));
        $video = $target->scheduledPost?->video;
        $brand = $video?->brand ?: \App\Models\Brand::first();
        $rawProductUrl = $target->product_url ?: ($video?->product_url ?: ($brand?->website ?? 'https://patenliayakkabilar.com'));

        // 0. Güvenlik Bariyeri: Gönderi metni kesinlikle boş veya sadece sistem etiketi olamaz!
        if ($caption === '' || preg_match('/^\d+[\.\s\-]+video$/i', $caption)) {
            $caption = $video?->custom_caption
                ?: ($video?->title && ! preg_match('/^\d+[\.\s\-]+video/i', $video->title) ? $video->title : '')
                ?: "Adım at, topuğa bas ve kaymaya başla! 🚀\n\n✨ Spor ayakkabı + paten konforu bir arada\n👟 Çocuk ve yetişkin modelleri\n🚚 Hızlı kargo • Güvenli alışveriş";
        }

        // 1. Şablon Güvencesi: Eğer metin tek satır/düz paragrafsa ve maddeli şablon içermiyorsa, estetik şablon yapısını uygula
        if (! str_contains($caption, "\n") || (! str_contains($caption, '✨') && ! str_contains($caption, '👟') && ! str_contains($caption, '•'))) {
            // Metin içine düz girmiş link ve hashtag kalıntılarını temizle (ayrı blok olarak eklenecek)
            $cleanHook = preg_replace('/https?:\/\/\S+/i', '', $caption);
            $cleanHook = preg_replace('/\bpatenliayakkabilar\.com\S*/i', '', $cleanHook);
            $cleanHook = preg_replace('/#[^\s#]+/u', '', $cleanHook);
            $cleanHook = preg_replace('/\b(?:hızlı kargo ile güvenli alışveriş için:?|sipariş için:?)\s*$/iu', '', trim($cleanHook));
            $cleanHook = trim($cleanHook);

            if (empty($cleanHook)) {
                $cleanHook = 'Adım at, topuğa bas ve kaymaya başla! 🚀';
            } elseif (! preg_match('/[✨🔥⚡🎁🌟🚀]$/u', $cleanHook)) {
                $cleanHook .= ' 🔥';
            }

            $bullets = "✨ Tek hareketle patene dönüşen spor ayakkabı\n👟 Çocuk & yetişkin patenli ayakkabı modelleri\n🚚 Hızlı kargo • Güvenli alışveriş";
            $caption = "{$cleanHook}\n\n{$bullets}";
        }

        // 2. Only append product_url if neither the full URL nor its bare domain is already in the caption
        if (! empty($rawProductUrl)) {
            $productUrl = trim((string) $rawProductUrl);
            $host = parse_url($productUrl, PHP_URL_HOST) ?: $productUrl;
            $bareDomain = preg_replace('/^www\./i', '', (string) $host);

            $urlAlreadyInCaption = ($productUrl !== '' && mb_stripos($caption, $productUrl) !== false)
                || ($bareDomain !== '' && mb_stripos($caption, $bareDomain) !== false);

            if (! $urlAlreadyInCaption) {
                $caption .= "\n\n🌐 " . $productUrl;
            }
        }

        // 3. Only append hashtags that are not already written inside the caption
        $targetHashtags = (! empty($target->hashtags) && is_array($target->hashtags))
            ? $target->hashtags
            : ['PatenliAyakkabı', 'TekerlekliAyakkabı', 'PatenliAyakkabılar'];

        $missingTags = [];
        foreach ($targetHashtags as $rawTag) {
            $cleanTag = ltrim(trim((string) $rawTag), '#');
            if ($cleanTag === '') {
                continue;
            }
            $formattedTag = '#' . $cleanTag;
            if (mb_stripos($caption, $formattedTag) === false) {
                $missingTags[] = $formattedTag;
            }
        }

        if (! empty($missingTags)) {
            $caption .= "\n\n" . implode(' ', $missingTags);
        }

        return trim($caption);
    }
}
