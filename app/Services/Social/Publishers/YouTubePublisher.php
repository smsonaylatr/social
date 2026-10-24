<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class YouTubePublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'youtube';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        $title = mb_substr($target->title ?: $video->title, 0, 100);
        $description = $this->buildFullCaption($target);

        if ($this->isSandboxToken($token)) {
            $randomYtId = 'dQw4w9WgXcQ' . rand(10, 99);
            return [
                'post_id' => $randomYtId,
                'post_url' => "https://youtube.com/shorts/{$randomYtId}",
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        // Resumable upload metadata session
        $metadata = [
            'snippet' => [
                'title' => $title,
                'description' => $description,
                'tags' => $target->hashtags ?: ['Shorts'],
                'categoryId' => '22', // People & Blogs
            ],
            'status' => [
                'privacyStatus' => 'public',
                'selfDeclaredMadeForKids' => false,
            ],
        ];

        $initResponse = Http::withToken($token)
            ->withHeaders([
                'X-Upload-Content-Type' => $video->mime_type ?: 'video/mp4',
                'Content-Type' => 'application/json; charset=UTF-8',
            ])
            ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', $metadata);

        // If 401 Unauthorized, automatically refresh token and retry once
        if ($initResponse->status() === 401 && $this->refreshToken($account)) {
            $account->load('credential');
            $token = $account->credential->access_token;
            $initResponse = Http::withToken($token)
                ->withHeaders([
                    'X-Upload-Content-Type' => $video->mime_type ?: 'video/mp4',
                    'Content-Type' => 'application/json; charset=UTF-8',
                ])
                ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', $metadata);
        }

        if (! $initResponse->successful()) {
            $body = $initResponse->body();
            if (str_contains($body, 'accessNotConfigured') || str_contains($body, 'SERVICE_DISABLED')) {
                preg_match('/project=(\d+)/', $body, $matches);
                $projectId = $matches[1] ?? '';
                $enableUrl = $projectId
                    ? "https://console.developers.google.com/apis/api/youtube.googleapis.com/overview?project={$projectId}"
                    : 'https://console.cloud.google.com/apis/library/youtube.googleapis.com';
                throw new \RuntimeException("Google Cloud projenizde 'YouTube Data API v3' servisi henüz etkinleştirilmemiş (Kapalı). Lütfen şu linke tıklayıp 'ETKİNLEŞTİR (ENABLE)' butonuna basın ve 1 dakika sonra tekrar deneyin: {$enableUrl}");
            }
            if (str_contains($body, 'youtubeSignupRequired')) {
                throw new \RuntimeException('Bu Google hesabına bağlı aktif bir YouTube kanalı bulunamadı. Lütfen youtube.com adresine girip kanal oluşturun.');
            }
            throw new \RuntimeException('YouTube Upload Session Hatası: ' . $body);
        }

        $uploadUrl = $initResponse->header('Location');
        $videoFilePath = Storage::disk($video->disk ?: 'public')->path($video->storage_path);

        if (! file_exists($videoFilePath)) {
            throw new \RuntimeException('Video dosyası yerel diskte bulunamadı: ' . $videoFilePath);
        }

        $stream = fopen($videoFilePath, 'r');
        $uploadResponse = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => $video->mime_type ?: 'video/mp4',
                'Content-Length' => filesize($videoFilePath),
            ])
            ->timeout(300)
            ->withBody($stream, $video->mime_type ?: 'video/mp4')
            ->put($uploadUrl);

        if (! $uploadResponse->successful()) {
            throw new \RuntimeException('YouTube Video Yükleme Hatası: ' . $uploadResponse->body());
        }

        $ytVideoId = $uploadResponse->json('id');

        return [
            'post_id' => $ytVideoId,
            'post_url' => "https://youtube.com/shorts/{$ytVideoId}",
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

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $credential->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            return false;
        }

        $credential->update([
            'access_token' => $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
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
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'id,snippet',
                'mine' => 'true',
            ]);

        return $response->successful();
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        if ($this->isSandboxToken($token)) {
            return ['views' => 3840, 'likes' => 415, 'comments' => 53, 'shares' => 0];
        }

        $response = Http::withToken($token)
            ->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'statistics',
                'id' => $externalPostId,
            ]);

        $stats = $response->json('items.0.statistics', []);

        return [
            'views' => (int) ($stats['viewCount'] ?? 0),
            'likes' => (int) ($stats['likeCount'] ?? 0),
            'comments' => (int) ($stats['commentCount'] ?? 0),
            'shares' => 0,
        ];
    }
}
