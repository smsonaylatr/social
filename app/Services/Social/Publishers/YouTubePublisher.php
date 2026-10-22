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

        if (str_starts_with($token, 'mock_') || config('app.env') === 'testing') {
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
                'tags' => $target->hashtags ?: ['Shorts', 'patenliayakkabi'],
                'categoryId' => '22', // People & Blogs
            ],
            'status' => [
                'privacyStatus' => 'public',
                'selfDeclaredMadeForKids' => false,
            ],
        ];

        $initResponse = Http::withToken($token)
            ->withHeaders([
                'X-Upload-Content-Type' => $video->mime_type,
                'Content-Type' => 'application/json; charset=UTF-8',
            ])
            ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', $metadata);

        if (! $initResponse->successful()) {
            throw new \RuntimeException('YouTube Upload Session Hatası: ' . $initResponse->body());
        }

        $uploadUrl = $initResponse->header('Location');
        $videoFilePath = Storage::disk($video->disk ?: 'public')->path($video->storage_path);

        if (! file_exists($videoFilePath)) {
            throw new \RuntimeException('Video dosyası yerel diskte bulunamadı: ' . $videoFilePath);
        }

        $stream = fopen($videoFilePath, 'r');
        $uploadResponse = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => $video->mime_type,
                'Content-Length' => filesize($videoFilePath),
            ])
            ->timeout(300)
            ->withBody($stream, $video->mime_type)
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
}
