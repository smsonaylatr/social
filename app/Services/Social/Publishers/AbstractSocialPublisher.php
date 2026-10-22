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
     * Format captions with hashtags and optional product link
     */
    protected function buildFullCaption(ScheduledPostTarget $target): string
    {
        $caption = $target->caption ?: '';
        if (! empty($target->hashtags) && is_array($target->hashtags)) {
            $tagsString = implode(' ', array_map(fn ($t) => '#' . ltrim($t, '#'), $target->hashtags));
            $caption .= "\n\n" . $tagsString;
        }

        if (! empty($target->product_url)) {
            $caption .= "\n" . $target->product_url;
        }

        return trim($caption);
    }
}
