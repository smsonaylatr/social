<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;

class TelegramPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'telegram';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        $caption = $this->safeTruncate($this->buildFullCaption($target), 1024);

        if ($this->isSandboxToken($token)) {
            $publishId = 'tg_msg_' . uniqid();
            return [
                'post_id' => $publishId,
                'post_url' => "https://t.me/{$account->username}",
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        $botToken = $this->getClientSecret() ?: $token;
        $chatId = str_starts_with($account->account_id, '@') || str_starts_with($account->account_id, '-')
            ? $account->account_id
            : '@' . ltrim($account->username ?: $account->account_id, '@');

        // Telegram Bot API: POST /bot{token}/sendVideo
        $response = Http::timeout(120)
            ->post("https://api.telegram.org/bot{$botToken}/sendVideo", [
                'chat_id' => $chatId,
                'video' => $video->url,
                'caption' => $caption,
                'supports_streaming' => true,
            ]);

        if (! $response->successful() || ! $response->json('ok')) {
            throw new \RuntimeException('Telegram Bot API Hatası: ' . $response->body());
        }

        $messageId = (string) $response->json('result.message_id');
        $cleanChannel = ltrim($chatId, '@');

        return [
            'post_id' => $messageId,
            'post_url' => "https://t.me/{$cleanChannel}/{$messageId}",
            'status' => 'published',
        ];
    }

    public function validateConnection(SocialAccount $account): bool
    {
        if (! parent::validateConnection($account)) {
            return false;
        }

        $token = $account->credential->access_token;
        if ($this->isSandboxToken($token)) {
            return true;
        }

        $botToken = $this->getClientSecret() ?: $token;
        $response = Http::get("https://api.telegram.org/bot{$botToken}/getMe");

        return $response->successful() && (bool) $response->json('ok');
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        return [
            'views' => 890,
            'likes' => 96,
            'comments' => 21,
            'shares' => 34,
        ];
    }
}
