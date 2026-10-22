<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class LinkedInPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'linkedin';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);

        if ($this->isSandboxToken($token)) {
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
            : ($account->account_type === 'profile'
                ? "urn:li:person:{$account->account_id}"
                : "urn:li:organization:{$account->account_id}");

        $headers = [
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version' => config('services.linkedin.api_version', '202401'),
        ];

        $videoUrn = null;
        $videoFilePath = Storage::disk($video->disk ?: 'public')->path($video->storage_path);
        if (file_exists($videoFilePath)) {
            $initUpload = Http::withToken($token)
                ->withHeaders($headers)
                ->post('https://api.linkedin.com/rest/videos?action=initializeUpload', [
                    'initializeUploadRequest' => [
                        'owner' => $authorUrn,
                        'fileSizeBytes' => filesize($videoFilePath),
                        'uploadCaptions' => false,
                        'uploadThumbnail' => false,
                    ],
                ]);

            if ($initUpload->successful()) {
                $videoUrn = $initUpload->json('value.video');
                $uploadInstructions = $initUpload->json('value.uploadInstructions', []);
                if (! empty($uploadInstructions[0]['uploadUrl'])) {
                    Http::withBody(file_get_contents($videoFilePath), 'application/octet-stream')
                        ->put($uploadInstructions[0]['uploadUrl']);
                }
            }
        }

        // LinkedIn Posts API (Restli 2.0.0)
        $postData = [
            'author' => $authorUrn,
            'commentary' => mb_substr($this->buildFullCaption($target), 0, 3000),
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        if ($videoUrn) {
            $postData['content'] = [
                'media' => [
                    'title' => mb_substr($target->title ?: $video->title, 0, 200),
                    'id' => $videoUrn,
                ],
            ];
        }

        $response = Http::withToken($token)
            ->withHeaders($headers)
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

        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $credential->refresh_token,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            return false;
        }

        $credential->update([
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token', $credential->refresh_token),
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
            ->get('https://api.linkedin.com/v2/userinfo');

        return $response->successful();
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        if ($this->isSandboxToken($token)) {
            return ['views' => 540, 'likes' => 62, 'comments' => 14, 'shares' => 11];
        }

        return [
            'views' => 0,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
        ];
    }
}
