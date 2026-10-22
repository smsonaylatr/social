<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;

class PinterestPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'pinterest';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);

        if ($this->isSandboxToken($token)) {
            $pinId = 'pin_' . uniqid();
            return [
                'post_id' => $pinId,
                'post_url' => "https://pinterest.com/pin/{$pinId}/",
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        // Pinterest API v5: Register media upload
        $regResponse = Http::withToken($token)
            ->post('https://api.pinterest.com/v5/media', [
                'media_type' => 'video',
            ]);

        if (! $regResponse->successful()) {
            throw new \RuntimeException('Pinterest Media Register Hatası: ' . $regResponse->body());
        }

        $mediaId = $regResponse->json('media_id');
        $uploadUrl = $regResponse->json('upload_url');
        $uploadParams = $regResponse->json('upload_parameters', []);

        // Direct upload to S3 uploadUrl
        $videoFilePath = storage_path('app/public/' . $video->storage_path);
        if (file_exists($videoFilePath) && $uploadUrl) {
            $uploadRequest = Http::asMultipart();
            foreach ($uploadParams as $k => $v) {
                $uploadRequest->attach($k, $v);
            }
            $uploadRequest->attach('file', file_get_contents($videoFilePath), 'video.mp4');
            $uploadRequest->post($uploadUrl);
        }

        // Create Pin
        $boardId = $account->account_id;
        $pinResponse = Http::withToken($token)
            ->post('https://api.pinterest.com/v5/pins', [
                'board_id' => $boardId,
                'title' => mb_substr($target->title ?: $video->title, 0, 100),
                'description' => mb_substr($this->buildFullCaption($target), 0, 500),
                'link' => $target->product_url ?: $video->product_url,
                'media_source' => [
                    'source_type' => 'video_id',
                    'cover_image_url' => $video->thumbnail_url,
                    'media_id' => $mediaId,
                ],
            ]);

        if (! $pinResponse->successful()) {
            throw new \RuntimeException('Pinterest Pin Oluşturma Hatası: ' . $pinResponse->body());
        }

        $pinId = $pinResponse->json('id');

        return [
            'post_id' => $pinId,
            'post_url' => "https://pinterest.com/pin/{$pinId}/",
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
        if (! $clientId || ! $clientSecret || ! $credential->refresh_token) {
            return false;
        }

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post('https://api.pinterest.com/v5/oauth/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $credential->refresh_token,
            ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            return false;
        }

        $credential->update([
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token', $credential->refresh_token),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 2592000)),
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
            ->get('https://api.pinterest.com/v5/user_account');

        return $response->successful();
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        if ($this->isSandboxToken($token)) {
            return ['views' => 620, 'likes' => 75, 'comments' => 9, 'shares' => 28];
        }

        $response = Http::withToken($token)
            ->get("https://api.pinterest.com/v5/pins/{$externalPostId}/analytics", [
                'start_date' => now()->subDays(30)->toDateString(),
                'end_date' => now()->toDateString(),
                'metric_types' => 'IMPRESSION,SAVE,PIN_CLICK',
            ]);

        $metrics = $response->json('all.summary_metrics', []);

        return [
            'views' => (int) ($metrics['IMPRESSION'] ?? 0),
            'likes' => (int) ($metrics['SAVE'] ?? 0),
            'comments' => 0,
            'shares' => (int) ($metrics['PIN_CLICK'] ?? 0),
        ];
    }
}
