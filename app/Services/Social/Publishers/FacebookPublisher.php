<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        // If mock / test token or local environment without internet access to graph.facebook.com
        if (str_starts_with($token, 'mock_') || config('app.env') === 'testing') {
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
}
