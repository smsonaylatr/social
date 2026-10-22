<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;

class LinkedInPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'linkedin';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);

        if (str_starts_with($token, 'mock_') || config('app.env') === 'testing') {
            $urn = 'urn:li:share:' . rand(7000000000000000000, 7999999999999999999);
            return [
                'post_id' => $urn,
                'post_url' => "https://www.linkedin.com/feed/update/{$urn}",
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        $authorUrn = str_starts_with($account->account_id, 'urn:li:')
            ? $account->account_id
            : "urn:li:organization:{$account->account_id}";

        // LinkedIn Posts API (Restli 2.0.0)
        $postData = [
            'author' => $authorUrn,
            'commentary' => $this->buildFullCaption($target),
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        $response = Http::withToken($token)
            ->withHeaders([
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version' => '202401',
            ])
            ->post('https://api.linkedin.com/rest/posts', $postData);

        if (! $response->successful()) {
            throw new \RuntimeException('LinkedIn Post API Hatası: ' . $response->body());
        }

        $postUrn = $response->header('x-restli-id') ?: $response->json('id');

        return [
            'post_id' => $postUrn,
            'post_url' => "https://www.linkedin.com/feed/update/{$postUrn}",
            'status' => 'published',
        ];
    }
}
