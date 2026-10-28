<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\Setting;
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

        // Otomasyon kuralında / hedefte belirlenen format (Öncelikli) veya kanal ayarı
        $isTextOnly = $target->post_type === 'text'
            || $target->scheduledPost?->post_type === 'text'
            || (Setting::get('partner_linkedin_post_mode') === 'text');

        if ($this->isSandboxToken($token)) {
            $urn = 'urn:li:share:' . rand(7000000000000000000, 7999999999999999999);
            return [
                'post_id' => $urn,
                'post_url' => "https://www.linkedin.com/feed/update/{$urn}",
                'status' => 'published',
                'is_mock' => true,
                'post_type' => $isTextOnly ? 'text' : 'video',
            ];
        }

        // Auto-resolve authentic LinkedIn author URN (urn:li:person:xxx) via OpenID /v2/userinfo
        $authorUrn = $this->resolveAuthorUrn($account, $token);
        $fullText = $this->buildFullCaption($target);

        // Strip https:// from patenliayakkabilar.com in commentary so LinkedIn displays "patenliayakkabilar.com" instead of shortening to "lnkd.in/..."
        $fullText = preg_replace('/https?:\/\/(www\.)?patenliayakkabilar\.com\/?/i', 'patenliayakkabilar.com', $fullText);

        $commentary = $this->safeTruncate($fullText, 3000);
        $productUrl = $target->product_url ?: ($video->product_url ?: 'https://patenliayakkabilar.com');
        $seoTitle = mb_substr($target->title ?: ($video->title ?: 'Patenli Ayakkabı ve Tekerlekli Spor Ayakkabı Modelleri | Patenli Ayakkabılar'), 0, 200);

        if ($isTextOnly) {
            $commentary = \App\Services\AI\OpenAiContentService::stripEmojis($commentary);
            $seoTitle = \App\Services\AI\OpenAiContentService::stripEmojis($seoTitle);
        }

        $headers = [
            'X-Restli-Protocol-Version' => '2.0.0',
        ];

        $assetUrn = null;

        if ($isTextOnly) {
            // Metin Postu Modu: Saf metin gönderisi (LinkedIn REST-LI UGC shareMediaCategory = NONE)
            $shareContent = [
                'shareCommentary' => [
                    'text' => $commentary,
                ],
                'shareMediaCategory' => 'NONE',
            ];
        } else {
            // 1. Upload video binary via LinkedIn v2 Assets API (Sadece video modunda)
            $binaryContent = $this->loadVideoBinary($video);

            if ($binaryContent !== null && strlen($binaryContent) > 0) {
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
                        $registerData = $registerResp->json() ?? [];
                        $candidateAsset = $registerData['value']['asset'] ?? null;
                        $uploadRequestInfo = $registerData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest'] ?? [];
                        $uploadUrl = $uploadRequestInfo['uploadUrl'] ?? null;

                        if ($candidateAsset && $uploadUrl) {
                            $extraHeaders = $uploadRequestInfo['headers'] ?? [];
                            $uploadHeaders = array_merge(
                                is_array($extraHeaders) ? $extraHeaders : [],
                                ['Content-Type' => 'application/octet-stream']
                            );

                            // Upload binary to LinkedIn mediaUpload endpoint (with Bearer token first, fallback without)
                            $uploadResp = Http::withToken($token)
                                ->withHeaders($uploadHeaders)
                                ->timeout(180)
                                ->withBody($binaryContent, 'application/octet-stream')
                                ->put($uploadUrl);

                            if (! $uploadResp->successful() && $uploadResp->status() !== 201) {
                                $uploadResp = Http::withHeaders($uploadHeaders)
                                ->timeout(180)
                                ->withBody($binaryContent, 'application/octet-stream')
                                ->put($uploadUrl);
                            }

                            if ($uploadResp->successful() || $uploadResp->status() === 201) {
                                $assetUrn = $candidateAsset;
                                $this->waitForVideoAssetReady($assetUrn, $token, $headers);
                            }
                        }
                    }
                } catch (\Throwable) {
                    $assetUrn = null;
                }
            }

            // 2. Publish via LinkedIn v2 ugcPosts API (VIDEO when video asset uploaded, otherwise ARTICLE)
            if ($assetUrn) {
                $shareContent = [
                    'shareCommentary' => [
                        'text' => $commentary,
                    ],
                    'shareMediaCategory' => 'VIDEO',
                    'media' => [
                        [
                            'status' => 'READY',
                            'description' => [
                                'text' => 'Patenli ayakkabı, tekerlekli ayakkabı ve çocuk ışıklı patenli spor ayakkabı modelleri — patenliayakkabilar.com',
                            ],
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

        // If VIDEO share is still finishing transcoding on LinkedIn, wait and retry VIDEO up to 4 times
        $videoRetries = 0;
        while (! $response->successful() && $assetUrn && $videoRetries < 4) {
            sleep(4);
            $response = Http::withToken($token)
                ->withHeaders($headers)
                ->timeout(30)
                ->post('https://api.linkedin.com/v2/ugcPosts', $ugcPayload);
            $videoRetries++;
        }

        if (! $response->successful()) {
            throw new \RuntimeException('LinkedIn Paylaşım Hatası (' . $response->status() . '): ' . $response->body());
        }

        $postUrn = $response->header('x-restli-id') ?: $response->json('id');

        return [
            'post_id' => $postUrn,
            'post_url' => "https://www.linkedin.com/feed/update/{$postUrn}",
            'status' => 'published',
            'post_type' => $isTextOnly ? 'text' : ($assetUrn ? 'video' : 'article'),
        ];
    }

    /**
     * Poll LinkedIn Assets API until video recipe status is AVAILABLE (ready to attach to ugcPosts).
     */
    protected function waitForVideoAssetReady(string $assetUrn, string $token, array $headers): void
    {
        $assetId = str_replace('urn:li:digitalmediaAsset:', '', $assetUrn);

        for ($i = 0; $i < 12; $i++) {
            sleep(3);

            try {
                $checkResp = Http::withToken($token)
                    ->withHeaders($headers)
                    ->timeout(10)
                    ->get("https://api.linkedin.com/v2/assets/{$assetId}");

                if (! $checkResp->successful()) {
                    return;
                }

                $recipeStatus = $checkResp->json('recipes.0.status') ?? $checkResp->json('status');
                if ($recipeStatus === 'AVAILABLE' || $recipeStatus === 'READY') {
                    return;
                }

                if (in_array($recipeStatus, ['PROCESSING_FAILED', 'CLIENT_ERROR', 'FAILED'], true)) {
                    return;
                }
            } catch (\Throwable) {
                return;
            }
        }
    }

    protected function loadVideoBinary(Video $video): ?string
    {
        if (! $video->storage_path) {
            return null;
        }

        $cleanPath = ltrim($video->storage_path, '/');
        $candidates = [];

        try {
            $candidates[] = Storage::disk($video->disk ?: 'public')->path($cleanPath);
        } catch (\Throwable) {
            // Ignore
        }

        $candidates[] = storage_path('app/public/' . $cleanPath);
        $candidates[] = public_path('storage/' . $cleanPath);

        foreach ($candidates as $path) {
            if ($path && file_exists($path) && filesize($path) > 0) {
                $content = @file_get_contents($path);
                if ($content !== false && strlen($content) > 0) {
                    return $content;
                }
            }
        }

        if ($video->url) {
            try {
                $resp = Http::timeout(30)->get($video->url);
                if ($resp->successful() && strlen($resp->body()) > 1000) {
                    return $resp->body();
                }
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
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
