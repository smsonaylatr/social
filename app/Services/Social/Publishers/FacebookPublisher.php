<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;

class FacebookPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'facebook';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        $caption = $this->buildFullCaption($target);
        $videoUrl = $video->url;

        if ($this->isSandboxToken($token)) {
            return [
                'post_id' => 'fb_post_' . uniqid(),
                'post_url' => "https://facebook.com/{$account->account_id}/videos/" . rand(1000000, 9999999),
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        // Meta Graph API: POST /{page-id}/videos
        $endpoint = "https://graph.facebook.com/v19.0/{$account->account_id}/videos";
        $response = Http::withToken($token)
            ->timeout(120)
            ->post($endpoint, [
                'description' => $caption,
                'title' => $target->title ?: $video->title,
                'file_url' => $videoUrl,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Facebook Graph API Hatası: ' . $response->body());
        }

        $result = $response->json();
        $videoId = $result['id'] ?? null;

        return [
            'post_id' => $videoId,
            'post_url' => "https://facebook.com/{$account->account_id}/videos/{$videoId}",
            'status' => 'published',
            'raw' => $result,
        ];
    }

    public function refreshToken(SocialAccount $account): bool
    {
        $credential = $account->credential;
        if (! $credential) {
            return false;
        }

        if ($this->isSandboxToken($credential->access_token)) {
            return parent::refreshToken($account);
        }

        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();
        if (! $clientId || ! $clientSecret) {
            return false;
        }

        $response = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'fb_exchange_token' => $credential->access_token,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            return false;
        }

        $credential->update([
            'access_token' => $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 5184000)),
        ]);

        return true;
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
            ->get("https://graph.facebook.com/v19.0/{$account->account_id}", [
                'fields' => 'id,name',
            ]);

        return $response->successful();
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        if ($this->isSandboxToken($token)) {
            return ['views' => 980, 'likes' => 112, 'comments' => 19, 'shares' => 27];
        }

        $response = Http::withToken($token)
            ->get("https://graph.facebook.com/v19.0/{$externalPostId}", [
                'fields' => 'views,likes.summary(true),comments.summary(true)',
            ]);

        return [
            'views' => (int) $response->json('views', 0),
            'likes' => (int) $response->json('likes.summary.total_count', 0),
            'comments' => (int) $response->json('comments.summary.total_count', 0),
            'shares' => 0,
        ];
    }
}
