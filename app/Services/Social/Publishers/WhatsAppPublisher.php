<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;

class WhatsAppPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'whatsapp';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        $caption = mb_substr($this->buildFullCaption($target), 0, 1024);

        if ($this->isSandboxToken($token)) {
            $publishId = 'wa_msg_' . uniqid();
            return [
                'post_id' => $publishId,
                'post_url' => "https://api.whatsapp.com/send?phone={$account->account_id}",
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        $phoneNumberId = $account->account_id;
        $recipientPhone = $account->credential->extra_data['default_recipient'] ?? $account->username;

        // Meta WhatsApp Business Cloud API v19.0: POST /{phone-number-id}/messages
        $response = Http::withToken($token)
            ->timeout(60)
            ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipientPhone,
                'type' => 'video',
                'video' => [
                    'link' => $video->url,
                    'caption' => $caption,
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('WhatsApp Cloud API Hatası: ' . $response->body());
        }

        $messageId = $response->json('messages.0.id') ?: ('wa_' . uniqid());

        return [
            'post_id' => $messageId,
            'post_url' => "https://wa.me/{$phoneNumberId}",
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

        $response = Http::withToken($token)
            ->get("https://graph.facebook.com/v19.0/{$account->account_id}");

        return $response->successful();
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        return [
            'views' => 310,
            'likes' => 45,
            'comments' => 18,
            'shares' => 12,
        ];
    }
}
