<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;

class TikTokPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'tiktok';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);

        if ($this->isSandboxToken($token)) {
            $publishId = 'tiktok_pub_' . uniqid();
            return [
                'post_id' => $publishId,
                'post_url' => "https://www.tiktok.com/@{$account->username}/video/" . rand(700000000, 799999999),
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        // TikTok Content Posting API v2: POST /v2/post/publish/video/init/
        $response = Http::withToken($token)
            ->timeout(60)
            ->post('https://open.tiktokapis.com/v2/post/publish/video/init/', [
                'post_info' => [
                    'title' => $this->safeTruncate($this->buildFullCaption($target), 2200),
                    'privacy_level' => 'PUBLIC_TO_EVERYONE',
                    'disable_duet' => false,
                    'disable_stitch' => false,
                    'disable_comment' => false,
                ],
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'video_url' => $video->url,
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('TikTok Content Posting Hatası: ' . $response->body());
        }

        $publishId = $response->json('data.publish_id');

        return [
            'post_id' => $publishId,
            'post_url' => "https://www.tiktok.com/@{$account->username}",
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

        $clientKey = $this->getClientId();
        $clientSecret = $this->getClientSecret();
        if (! $clientKey || ! $clientSecret || ! $credential->refresh_token) {
            return false;
        }

        $response = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => $clientKey,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $credential->refresh_token,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            return false;
        }

        $credential->update([
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token', $credential->refresh_token),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 86400)),
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
            ->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,display_name',
            ]);

        return $response->successful();
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        if ($this->isSandboxToken($token)) {
            return ['views' => 1420, 'likes' => 185, 'comments' => 24, 'shares' => 38];
        }

        $response = Http::withToken($token)
            ->post('https://open.tiktokapis.com/v2/video/query/?fields=id,view_count,like_count,comment_count,share_count', [
                'filters' => [
                    'video_ids' => [$externalPostId],
                ],
            ]);

        $videoData = $response->json('data.videos.0', []);

        return [
            'views' => (int) ($videoData['view_count'] ?? 0),
            'likes' => (int) ($videoData['like_count'] ?? 0),
            'comments' => (int) ($videoData['comment_count'] ?? 0),
            'shares' => (int) ($videoData['share_count'] ?? 0),
        ];
    }
}
