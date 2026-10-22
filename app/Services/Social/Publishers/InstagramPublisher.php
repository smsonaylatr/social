<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'instagram';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        $caption = $this->buildFullCaption($target);
        $videoUrl = $video->url;

        if (str_starts_with($token, 'mock_') || config('app.env') === 'testing') {
            $randomSlug = \Illuminate\Support\Str::random(11);
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
}
