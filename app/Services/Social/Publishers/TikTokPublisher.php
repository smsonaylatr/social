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

        if (str_starts_with($token, 'mock_') || config('app.env') === 'testing') {
            $publishId = 'tiktok_pub_' . uniqid();
            return [
                'post_id' => $publishId,
                'post_url' => "https://www.tiktok.com/@{$account->username}/video/" . rand(700000000, 799999999),
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        // TikTok Content Posting API: POST /v2/post/publish/video/init/
        $response = Http::withToken($token)
            ->post('https://open.tiktokapis.com/v2/post/publish/video/init/', [
                'post_info' => [
                    'title' => $this->buildFullCaption($target),
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
}
