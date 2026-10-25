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
        @set_time_limit(180);

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

        // Auto-resolve authentic LinkedIn author URN (urn:li:person:xxx) via OpenID /v2/userinfo
        $authorUrn = $this->resolveAuthorUrn($account, $token);
        $fullText = $this->buildFullCaption($target);

        // Ensure full https:// link in commentary for clickable SEO backlink on LinkedIn
        if (! str_contains($fullText, 'https://') && ! str_contains($fullText, 'http://')) {
            $fullText = preg_replace('/\b(patenliayakkabilar\.com)\b/i', 'https://$1', $fullText);
        }

        $commentary = mb_substr($fullText, 0, 3000);
        $productUrl = $target->product_url ?: ($video->product_url ?: 'https://patenliayakkabilar.com');
        $seoTitle = mb_substr($target->title ?: ($video->title ?: 'Patenli Ayakkabı ve Tekerlekli Spor Ayakkabı Modelleri | Patenli Ayakkabılar'), 0, 200);

        $headers = [
            'X-Restli-Protocol-Version' => '2.0.0',
        ];

        // 1. Upload video binary via LinkedIn v2 Assets API
        $assetUrn = null;
        if ($video->storage_path) {
            $videoFilePath = Storage::disk($video->disk ?: 'public')->path($video->storage_path);
            if (file_exists($videoFilePath) && filesize($videoFilePath) > 0) {
                try {
                    $registerResp = Http::withToken($token)
                        ->withHeaders($headers)
                        ->timeout(30)
                        ->post('https://api.linkedin.com/v2/assets?action=registerUpload', [
                            'registerUploadRequest' => [
                                'recipes' => ['urn:li:digitalmediaRecipe:feedshare-video'],
                                'owner' => $authorUrn,
                                'serviceRelationships' => [
                                    [
                                        'relationshipType' => 'OWNER',
                                        'identifier' => 'urn:li:userGeneratedContent',
                                    ],
                                ],
                            ],
                        ]);

                    if ($registerResp->successful()) {
                        $candidateAsset = $registerResp->json('value.asset');
                        $uploadUrl = $registerResp->json('value.uploadMechanism.com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest.uploadUrl');

                        if ($candidateAsset && $uploadUrl) {
                            $binaryContent = file_get_contents($videoFilePath);
                            $uploadResp = Http::withToken($token)
                                ->withHeaders([
                                    'Content-Type' => $video->mime_type ?: 'video/mp4',
                                ])
                                ->timeout(180)
                                ->withBody($binaryContent, $video->mime_type ?: 'video/mp4')
                                ->put($uploadUrl);

                            if ($uploadResp->successful() || $uploadResp->status() === 201) {
                                $assetUrn = $candidateAsset;
                                sleep(3); // Brief pause for LinkedIn asset registration
                            }
                        }
                    }
                } catch (\Throwable) {
                    $assetUrn = null;
                }
            }
        }

        // 2. Publish via LinkedIn v2 ugcPosts API (VIDEO first, fallback to SEO ARTICLE Card)
        if ($assetUrn) {
            $shareContent = [
                'shareCommentary' => [
                    'text' => $commentary,
                ],
                'shareMediaCategory' => 'VIDEO',
                'media' => [
                    [
                        'status' => 'READY',
                        'media' => $assetUrn,
                        'title' => [
                            'text' => $seoTitle,
                        ],
                    ],
                ],
            ];
        } else {
            $shareContent = [
                'shareCommentary' => [
                    'text' => $commentary,
                ],
                'shareMediaCategory' => 'ARTICLE',
                'media' => [
                    [
                        'status' => 'READY',
                        'originalUrl' => $productUrl,
                        'title' => [
                            'text' => $seoTitle,
                        ],
                        'description' => [
                            'text' => 'Patenli ayakkabı, tekerlekli ayakkabı ve çocuk ışıklı patenli spor ayakkabı modelleri — patenliayakkabilar.com',
                        ],
                    ],
                ],
            ];
        }

        $ugcPayload = [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => $shareContent,
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $response = Http::withToken($token)
            ->withHeaders($headers)
            ->timeout(30)
            ->post('https://api.linkedin.com/v2/ugcPosts', $ugcPayload);

        // Fallback: If VIDEO share fails, publish as SEO ARTICLE Card with clickable link
        if (! $response->successful() && $assetUrn) {
            $ugcPayload['specificContent']['com.linkedin.ugc.ShareContent'] = [
                'shareCommentary' => ['text' => $commentary],
                'shareMediaCategory' => 'ARTICLE',
                'media' => [
                    [
                        'status' => 'READY',
                        'originalUrl' => $productUrl,
                        'title' => ['text' => $seoTitle],
                        'description' => [
                            'text' => 'Patenli ayakkabı, tekerlekli ayakkabı ve çocuk ışıklı patenli spor ayakkabı modelleri — patenliayakkabilar.com',
                        ],
                    ],
                ],
            ];
            $response = Http::withToken($token)
                ->withHeaders($headers)
                ->timeout(30)
                ->post('https://api.linkedin.com/v2/ugcPosts', $ugcPayload);
        }

        if (! $response->successful()) {
            throw new \RuntimeException('LinkedIn Paylaşım Hatası (' . $response->status() . '): ' . $response->body());
        }

        $postUrn = $response->header('x-restli-id') ?: $response->json('id');

        return [
            'post_id' => $postUrn,
            'post_url' => "https://www.linkedin.com/feed/update/{$postUrn}",
            'status' => 'published',
        ];
    }

    /**
     * Automatically resolve urn:li:person:{sub} using /v2/userinfo
     */
    protected function resolveAuthorUrn(SocialAccount $account, string $token): string
    {
        try {
            $infoResp = Http::withToken($token)
                ->timeout(10)
                ->get('https://api.linkedin.com/v2/userinfo');

            if ($infoResp->successful() && $infoResp->json('sub')) {
                $personUrn = 'urn:li:person:' . $infoResp->json('sub');
                if ($account->account_id !== $personUrn) {
                    $account->update(['account_id' => $personUrn]);
                }
                return $personUrn;
            }
        } catch (\Throwable) {
            // Fallback below
        }

        if (str_starts_with((string) $account->account_id, 'urn:li:')) {
            return (string) $account->account_id;
        }

        return "urn:li:person:{$account->account_id}";
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
