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

        if (str_starts_with($token, 'mock_') || config('app.env') === 'testing') {
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
        $uploadParams = $regResponse->json('upload_parameters');

        // Direct upload to S3 uploadUrl
        $videoFilePath = storage_path('app/public/' . $video->storage_path);
        if (file_exists($videoFilePath)) {
            $uploadRequest = Http::asMultipart();
            foreach ($uploadParams as $k => $v) {
                $uploadRequest->attach($k, $v);
            }
            $uploadRequest->attach('file', file_get_contents($videoFilePath), 'video.mp4');
            $uploadRequest->post($uploadUrl);
        }

        // Create Pin
        $boardId = $account->account_id; // Default selected board id
        $pinResponse = Http::withToken($token)
            ->post('https://api.pinterest.com/v5/pins', [
                'board_id' => $boardId,
                'title' => $target->title ?: $video->title,
                'description' => $this->buildFullCaption($target),
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
}
