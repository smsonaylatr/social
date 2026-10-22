<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InstagramPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'instagram';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        $caption = mb_substr($this->buildFullCaption($target), 0, 2200);
        $videoUrl = $video->url;

        if ($this->isSandboxToken($token)) {
            $randomSlug = Str::random(11);
            return [
                'post_id' => 'ig_reel_' . uniqid(),
                'post_url' => "https://instagram.com/reel/{$randomSlug}/",
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        // 1. Create Media Container: POST /{ig-user-id}/media?media_type=REELS
        $containerResponse = Http::withToken($token)
            ->timeout(60)
            ->post("https://graph.facebook.com/v19.0/{$account->account_id}/media", [
                'media_type' => 'REELS',
                'video_url' => $videoUrl,
                'caption' => $caption,
                'share_to_feed' => true,
            ]);

        if (! $containerResponse->successful()) {
            throw new \RuntimeException('Instagram Media Container Hatası: ' . $containerResponse->body());
        }

        $containerId = $containerResponse->json('id');

        // 2. Poll container status until FINISHED
        $isReady = false;
        $maxRetries = 10;
        for ($i = 0; $i < $maxRetries; $i++) {
            sleep(3);
            $statusResponse = Http::withToken($token)
                ->get("https://graph.facebook.com/v19.0/{$containerId}?fields=status_code");

            $statusCode = $statusResponse->json('status_code');
            if ($statusCode === 'FINISHED') {
                $isReady = true;
                break;
            } elseif ($statusCode === 'ERROR') {
                throw new \RuntimeException('Instagram Reels video işleme hatası oluştu.');
            }
        }

        if (! $isReady) {
            throw new \RuntimeException('Instagram video işleme zaman aşımına uğradı.');
        }

        // 3. Publish Media: POST /{ig-user-id}/media_publish
        $publishResponse = Http::withToken($token)
            ->post("https://graph.facebook.com/v19.0/{$account->account_id}/media_publish", [
                'creation_id' => $containerId,
            ]);

        if (! $publishResponse->successful()) {
            throw new \RuntimeException('Instagram Yayınlama Hatası: ' . $publishResponse->body());
        }

        $mediaId = $publishResponse->json('id');

        return [
            'post_id' => $mediaId,
            'post_url' => "https://instagram.com/p/{$mediaId}/",
            'status' => 'published',
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
                'fields' => 'id,username',
            ]);

        return $response->successful();
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        if ($this->isSandboxToken($token)) {
            return ['views' => 2310, 'likes' => 310, 'comments' => 42, 'shares' => 65];
        }

        $response = Http::withToken($token)
            ->get("https://graph.facebook.com/v19.0/{$externalPostId}", [
                'fields' => 'like_count,comments_count',
            ]);

        return [
            'views' => 0,
            'likes' => (int) $response->json('like_count', 0),
            'comments' => (int) $response->json('comments_count', 0),
            'shares' => 0,
        ];
    }
}
