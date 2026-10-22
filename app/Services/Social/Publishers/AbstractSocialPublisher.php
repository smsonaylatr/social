<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\Social\Contracts\SocialPublisherInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractSocialPublisher implements SocialPublisherInterface
{
    abstract public function getPlatformCode(): string;

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
        return [
            'remaining_today' => 20,
            'is_limited' => false,
        ];
    }

    /**
     * Format captions with hashtags
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
