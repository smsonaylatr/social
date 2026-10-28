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

        $seoService = app(\App\Services\SEO\HashtagOptimizationService::class);
        $seoKeywords = $seoService->getRelevantKeywords($video ?: new Video(), $this->getPlatformCode(), limit: 4);

        $kw1 = ! empty($seoKeywords[0]) ? mb_convert_case($seoKeywords[0], MB_CASE_TITLE, 'UTF-8') : 'Patenli Ayakkabı';
        $kw2 = ! empty($seoKeywords[1]) ? mb_convert_case($seoKeywords[1], MB_CASE_TITLE, 'UTF-8') : 'Tekerlekli Spor Ayakkabı';

        // 0. Güvenlik Bariyeri: Gönderi metni kesinlikle boş veya sadece sistem etiketi olamaz!
        if ($caption === '' || preg_match('/^\d+[\.\s\-]+video$/i', $caption)) {
            $caption = $video?->custom_caption
                ?: ($video?->title && ! preg_match('/^\d+[\.\s\-]+video/i', $video->title) ? $video->title : '')
                ?: "Adım at, topuğa bas ve kaymaya başla! 🚀\n\n✨ {$kw1} modelleri ile eğlence ve konfor bir arada\n👟 {$kw2} ve günlük spor ayakkabı deneyimi\n🚚 Hızlı kargo • Güvenli alışveriş";
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

            $bullets = "✨ Tek hareketle patene dönüşen {$kw1}\n👟 {$kw2} modelleri\n🚚 Hızlı kargo • Güvenli alışveriş";
            $caption = "{$cleanHook}\n\n{$bullets}";
        } else {
            // Eğer metinde SEO hafızasındaki anahtar kelimelerden hiçbiri geçmiyorsa, maddelere doğal olarak ekle
            $hasKeyword = false;
            foreach ($seoKeywords as $keyword) {
                if (mb_stripos($caption, $keyword) !== false) {
                    $hasKeyword = true;
                    break;
                }
            }

            if (! $hasKeyword && ! empty($seoKeywords)) {
                $seoLine = "✨ {$kw1} ve {$kw2} modelleri";
                // Eğer şablonda ✨ varsa ve ilk satır kancaysa, maddeler arasına dahil et
                if (! str_contains($caption, $kw1)) {
                    $caption = preg_replace('/(\n\n✨)/u', "\n\n{$seoLine}\n✨", $caption, 1);
                }
            }
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

        // 3. Hashtag havuzundan gelen platforma özel dinamik etiketleri ekle (SEO hafızası öncelikli)
        $targetHashtags = (! empty($target->hashtags) && is_array($target->hashtags))
            ? $target->hashtags
            : $seoService->getOptimizedHashtags($video ?: new Video(), $this->getPlatformCode());

        // Metin gövdesinde eski/dağınık hashtag kalıntıları varsa temizle
        $captionWithoutTags = preg_replace('/#[^\s#]+/u', '', $caption);
        $caption = trim(preg_replace('/\n{3,}/', "\n\n", (string) $captionWithoutTags));

        // Platformun izin verdiği maksimum karakter sınırını belirle
        $platformEnum = \App\Enums\SocialPlatformCode::tryFrom($this->getPlatformCode());
        $maxPlatformLength = $platformEnum ? $platformEnum->maxCaptionLength() : 2200;

        $formattedTags = [];
        foreach ($targetHashtags as $rawTag) {
            $cleanTag = $seoService->normalizeTag((string) $rawTag);
            if (! $seoService->isTagValidForPlatform($cleanTag, $this->getPlatformCode())) {
                continue;
            }
            if (in_array('#' . $cleanTag, $formattedTags)) {
                continue;
            }

            // Karakter bütçesi kontrolü: Yeni etiketin platform sınırını aşıp aşmadığını test et
            $candidateTag = '#' . $cleanTag;
            $testTags = array_merge($formattedTags, [$candidateTag]);
            $testFullCaption = $caption . "\n\n" . implode(' ', $testTags);

            // Eğer etiketi ekleyince sınır aşılıyorsa, bu etiketi ve sonrakileri ekleme (kesinlikle yarım kesilmez!)
            if (mb_strlen($testFullCaption, 'UTF-8') > $maxPlatformLength) {
                break;
            }

            $formattedTags[] = $candidateTag;
        }

        if (! empty($formattedTags)) {
            $caption .= "\n\n" . implode(' ', $formattedTags);
        }

        return $this->safeTruncate(trim($caption), $maxPlatformLength);
    }

    /**
     * Metni kelime, emoji, URL ve hashtag bütünlüğünü bozmadan güvenle sınırlar.
     * Asla "#pat" gibi yarım kesilmiş hashtag veya sonda tek başına "#" bırakmaz.
     */
    public function safeTruncate(string $text, int $maxLength): string
    {
        $text = trim($text);
        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }

        // 1. Eğer metnin sonundaki hashtagler yüzünden sınır aşılıyorsa, hashtagleri sondan teker teker çıkar
        while (mb_strlen($text, 'UTF-8') > $maxLength && preg_match('/\s+#[^\s#]+$/u', $text)) {
            $text = preg_replace('/\s+#[^\s#]+$/u', '', $text);
        }

        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return trim($text);
        }

        // 2. Eğer hala sınırdan uzunsa, kelime veya satır sınırından güvenle kes
        $truncated = mb_substr($text, 0, $maxLength, 'UTF-8');

        $lastSpace = mb_strrpos($truncated, ' ', 0, 'UTF-8');
        $lastNewline = mb_strrpos($truncated, "\n", 0, 'UTF-8');
        $lastBreak = max($lastSpace !== false ? $lastSpace : 0, $lastNewline !== false ? $lastNewline : 0);

        if ($lastBreak > (int) ($maxLength * 0.7)) {
            $truncated = mb_substr($truncated, 0, $lastBreak, 'UTF-8');
        }

        // 3. Kesilme sonucu sonda kalan yarım hashtag (#pat vb.) veya tek başına # işaretini temizle
        $truncated = preg_replace('/\s*#[^\s]*$/u', '', $truncated);

        return trim($truncated);
    }
}
