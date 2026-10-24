<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstagramPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'instagram';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        @set_time_limit(180);

        $token = $this->getAccessToken($account);
        $caption = mb_substr($this->buildFullCaption($target), 0, 2200);
        $videoUrl = $video->url;

        if ($this->isSandboxToken($token)) {
            $randomSlug = Str::random(11);
            return [
                'post_id' => 'ig_reel_' . uniqid(),
                'post_url' => "https://instagram.com/reel/{$randomSlug}/",
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        // Automatically resolve the true Instagram Business Account ID (178414...) if account_id is a FB User/Page/App ID
        $resolved = $this->resolveInstagramAccount($account, $token);
        $igAccountId = $resolved['account_id'];
        $activeToken = $resolved['token'];
        $baseUrl = $resolved['base_url'];

        // 1. Create Media Container: Try URL-based first, fallback to Resumable Direct Upload if URL fails
        $containerId = null;
        $containerResponse = Http::withToken($activeToken)
            ->timeout(60)
            ->post("{$baseUrl}/{$igAccountId}/media", [
                'media_type' => 'REELS',
                'video_url' => $videoUrl,
                'caption' => $caption,
                'share_to_feed' => true,
            ]);

        if ($containerResponse->successful() && $containerResponse->json('id')) {
            $containerId = $containerResponse->json('id');
        } else {
            // If error is 100/33 (wrong object ID) and we didn't yet force discovery, force discovery now
            $errCode = (int) $containerResponse->json('error.code', 0);
            $errSubcode = (int) $containerResponse->json('error.error_subcode', 0);

            if ($errCode === 100 || $errSubcode === 33) {
                $forced = $this->resolveInstagramAccount($account, $token, true);
                if ($forced['account_id'] !== $igAccountId) {
                    $igAccountId = $forced['account_id'];
                    $activeToken = $forced['token'];
                    $baseUrl = $forced['base_url'];

                    $containerResponse = Http::withToken($activeToken)
                        ->timeout(60)
                        ->post("{$baseUrl}/{$igAccountId}/media", [
                            'media_type' => 'REELS',
                            'video_url' => $videoUrl,
                            'caption' => $caption,
                            'share_to_feed' => true,
                        ]);

                    if ($containerResponse->successful() && $containerResponse->json('id')) {
                        $containerId = $containerResponse->json('id');
                    }
                }
            }

            // Try Resumable Direct Local File Upload if URL fetch failed on Meta side
            if (! $containerId && $errCode !== 100 && $errSubcode !== 33) {
                $containerId = $this->tryResumableUpload($video, $igAccountId, $activeToken, $caption, $baseUrl);
            }

            if (! $containerId) {
                throw new \RuntimeException($this->buildFriendlyErrorMessage($containerResponse->json() ?: $containerResponse->body(), $igAccountId, $token, $forced['diagnostics'] ?? $resolved['diagnostics'] ?? []));
            }
        }

        // 2. Poll container status until FINISHED
        $isReady = false;
        $maxRetries = 18;
        for ($i = 0; $i < $maxRetries; $i++) {
            sleep(3);
            $statusResponse = Http::withToken($activeToken)
                ->get("{$baseUrl}/{$containerId}", [
                    'fields' => 'status_code,status',
                ]);

            $statusCode = $statusResponse->json('status_code');
            if ($statusCode === 'FINISHED') {
                $isReady = true;
                break;
            } elseif ($statusCode === 'ERROR' || $statusCode === 'EXPIRED') {
                // Fallback to resumable local upload if video_url download failed during processing
                $resumableContainerId = $this->tryResumableUpload($video, $igAccountId, $activeToken, $caption, $baseUrl);
                if ($resumableContainerId) {
                    $containerId = $resumableContainerId;
                    for ($j = 0; $j < $maxRetries; $j++) {
                        sleep(3);
                        $retryStatus = Http::withToken($activeToken)
                            ->get("{$baseUrl}/{$containerId}", ['fields' => 'status_code,status']);
                        if ($retryStatus->json('status_code') === 'FINISHED') {
                            $isReady = true;
                            break 2;
                        } elseif (in_array($retryStatus->json('status_code'), ['ERROR', 'EXPIRED'], true)) {
                            break;
                        }
                    }
                }

                $statusDetail = $statusResponse->json('status') ?: $statusResponse->body();
                throw new \RuntimeException("Instagram Reels video işleme hatası: {$statusDetail}");
            }
        }

        if (! $isReady) {
            throw new \RuntimeException('Instagram video işleme zaman aşımına uğradı.');
        }

        // 3. Publish Media: POST /{ig-user-id}/media_publish
        $publishResponse = Http::withToken($activeToken)
            ->timeout(60)
            ->post("{$baseUrl}/{$igAccountId}/media_publish", [
                'creation_id' => $containerId,
            ]);

        if (! $publishResponse->successful()) {
            throw new \RuntimeException('Instagram Yayınlama Hatası: ' . $publishResponse->body());
        }

        $mediaId = $publishResponse->json('id');

        // Try to fetch permalink
        $permalink = "https://instagram.com/p/{$mediaId}/";
        try {
            $permResp = Http::withToken($activeToken)->get("{$baseUrl}/{$mediaId}", ['fields' => 'permalink']);
            if ($permResp->successful() && $permResp->json('permalink')) {
                $permalink = $permResp->json('permalink');
            }
        } catch (\Throwable) {
            // Keep default permalink
        }

        return [
            'post_id' => $mediaId,
            'post_url' => $permalink,
            'status' => 'published',
        ];
    }

    /**
     * Automatically discover the true Instagram Business Account ID (178414...) from the token
     */
    public function resolveInstagramAccount(SocialAccount $account, string $token, bool $forceDiscovery = false): array
    {
        $currentId = trim((string) $account->account_id);
        $diagnostics = [
            'scopes' => [],
            'pages_found' => 0,
            'app_id' => null,
            'user_id' => null,
        ];

        // 1. Instagram Login API Token (starts with IG...)
        if (str_starts_with($token, 'IG')) {
            try {
                $igMe = Http::withToken($token)
                    ->timeout(15)
                    ->get('https://graph.instagram.com/v21.0/me', [
                        'fields' => 'user_id,id,username,name',
                    ]);

                if ($igMe->successful()) {
                    $realId = (string) ($igMe->json('user_id') ?: $igMe->json('id') ?: $currentId);
                    $this->persistDiscoveredAccount($account, $realId, $igMe->json('name'), $igMe->json('username'));
                    return [
                        'account_id' => $realId,
                        'token' => $token,
                        'base_url' => 'https://graph.instagram.com/v21.0',
                        'diagnostics' => $diagnostics,
                    ];
                }
            } catch (\Throwable) {
                // Fallback below
            }
        }

        // 2. If currentId already starts with 178414 (standard IG Business Account ID prefix) and not forced, verify quickly
        if (! $forceDiscovery && str_starts_with($currentId, '178414')) {
            try {
                $check = Http::withToken($token)
                    ->timeout(10)
                    ->get("https://graph.facebook.com/v19.0/{$currentId}", [
                        'fields' => 'id,username,name',
                    ]);
                if ($check->successful() && $check->json('id')) {
                    return [
                        'account_id' => $currentId,
                        'token' => $token,
                        'base_url' => 'https://graph.facebook.com/v19.0',
                        'diagnostics' => $diagnostics,
                    ];
                }
            } catch (\Throwable) {
                // Proceed to discovery
            }
        }

        // 3. Discovery Step A: Check /me/accounts (Facebook Pages with instagram_business_account or connected_instagram_account)
        try {
            $pagesResp = Http::withToken($token)
                ->timeout(15)
                ->get('https://graph.facebook.com/v19.0/me/accounts', [
                    'fields' => 'id,name,access_token,instagram_business_account{id,name,username,profile_picture_url},connected_instagram_account{id,name,username,profile_picture_url}',
                ]);

            if ($pagesResp->successful()) {
                $pages = $pagesResp->json('data', []);
                $diagnostics['pages_found'] = count($pages);

                foreach ($pages as $page) {
                    $ig = $page['instagram_business_account'] ?? $page['connected_instagram_account'] ?? null;
                    if (! empty($ig['id'])) {
                        $this->persistDiscoveredAccount($account, (string) $ig['id'], $ig['name'] ?? null, $ig['username'] ?? null);
                        return [
                            'account_id' => (string) $ig['id'],
                            'token' => $token,
                            'base_url' => 'https://graph.facebook.com/v19.0',
                            'diagnostics' => $diagnostics,
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            // Continue discovery
        }

        // 4. Discovery Step B: Inspect /debug_token granular_scopes (works even when /me/accounts is empty!)
        try {
            $debugResp = Http::timeout(15)->get('https://graph.facebook.com/v19.0/debug_token', [
                'input_token' => $token,
                'access_token' => $token,
            ]);

            if ($debugResp->successful()) {
                $debugData = $debugResp->json('data', []);
                $diagnostics['scopes'] = $debugData['scopes'] ?? [];
                $diagnostics['app_id'] = (string) ($debugData['app_id'] ?? '');
                $diagnostics['user_id'] = (string) ($debugData['user_id'] ?? '');

                $granularScopes = $debugData['granular_scopes'] ?? [];

                // First check direct Instagram target_ids in granular_scopes
                foreach ($granularScopes as $gs) {
                    $scopeName = $gs['scope'] ?? '';
                    $targetIds = $gs['target_ids'] ?? [];
                    if (in_array($scopeName, ['instagram_content_publish', 'instagram_basic', 'instagram_manage_comments', 'instagram_manage_insights'], true) && ! empty($targetIds)) {
                        $candidateIgId = (string) $targetIds[0];
                        $igInfo = Http::withToken($token)
                            ->timeout(10)
                            ->get("https://graph.facebook.com/v19.0/{$candidateIgId}", [
                                'fields' => 'id,name,username',
                            ])->json();

                        $this->persistDiscoveredAccount($account, $candidateIgId, $igInfo['name'] ?? null, $igInfo['username'] ?? null);
                        return [
                            'account_id' => $candidateIgId,
                            'token' => $token,
                            'base_url' => 'https://graph.facebook.com/v19.0',
                            'diagnostics' => $diagnostics,
                        ];
                    }
                }

                // Second check Page target_ids in granular_scopes and query each Page for its linked Instagram account
                $pageTargetIds = [];
                foreach ($granularScopes as $gs) {
                    $scopeName = $gs['scope'] ?? '';
                    if (in_array($scopeName, ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'pages_manage_metadata'], true)) {
                        foreach ($gs['target_ids'] ?? [] as $pid) {
                            $pageTargetIds[(string) $pid] = (string) $pid;
                        }
                    }
                }

                foreach ($pageTargetIds as $pageId) {
                    $diagnostics['pages_found'] = max($diagnostics['pages_found'], count($pageTargetIds));
                    $pageInfo = Http::withToken($token)
                        ->timeout(10)
                        ->get("https://graph.facebook.com/v19.0/{$pageId}", [
                            'fields' => 'id,name,access_token,instagram_business_account{id,name,username},connected_instagram_account{id,name,username}',
                        ]);

                    if ($pageInfo->successful()) {
                        $ig = $pageInfo->json('instagram_business_account') ?? $pageInfo->json('connected_instagram_account');
                        if (! empty($ig['id'])) {
                            $this->persistDiscoveredAccount($account, (string) $ig['id'], $ig['name'] ?? null, $ig['username'] ?? null);
                            return [
                                'account_id' => (string) $ig['id'],
                                'token' => $token,
                                'base_url' => 'https://graph.facebook.com/v19.0',
                                'diagnostics' => $diagnostics,
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Continue discovery
        }

        // 5. Discovery Step C: Query current account_id or /me as a Facebook Page directly
        foreach (array_unique(array_filter([$currentId, 'me'])) as $nodeId) {
            if ($nodeId !== 'me' && ! ctype_digit($nodeId)) {
                continue;
            }
            try {
                $nodeResp = Http::withToken($token)
                    ->timeout(10)
                    ->get("https://graph.facebook.com/v19.0/{$nodeId}", [
                        'fields' => 'id,name,instagram_business_account{id,name,username},connected_instagram_account{id,name,username}',
                    ]);

                if ($nodeResp->successful()) {
                    $ig = $nodeResp->json('instagram_business_account') ?? $nodeResp->json('connected_instagram_account');
                    if (! empty($ig['id'])) {
                        $this->persistDiscoveredAccount($account, (string) $ig['id'], $ig['name'] ?? null, $ig['username'] ?? null);
                        return [
                            'account_id' => (string) $ig['id'],
                            'token' => $token,
                            'base_url' => 'https://graph.facebook.com/v19.0',
                            'diagnostics' => $diagnostics,
                        ];
                    }
                }
            } catch (\Throwable) {
                // Continue
            }
        }

        // 6. Discovery Step D: Check /me/businesses for owned/client Instagram Business Accounts
        try {
            $bizResp = Http::withToken($token)
                ->timeout(12)
                ->get('https://graph.facebook.com/v19.0/me/businesses', [
                    'fields' => 'id,name,instagram_business_accounts{id,name,username},owned_pages{id,name,instagram_business_account{id,name,username},connected_instagram_account{id,name,username}}',
                ]);

            if ($bizResp->successful()) {
                foreach ($bizResp->json('data', []) as $biz) {
                    if (! empty($biz['instagram_business_accounts']['data'][0]['id'])) {
                        $ig = $biz['instagram_business_accounts']['data'][0];
                        $this->persistDiscoveredAccount($account, (string) $ig['id'], $ig['name'] ?? null, $ig['username'] ?? null);
                        return [
                            'account_id' => (string) $ig['id'],
                            'token' => $token,
                            'base_url' => 'https://graph.facebook.com/v19.0',
                            'diagnostics' => $diagnostics,
                        ];
                    }
                    foreach ($biz['owned_pages']['data'] ?? [] as $op) {
                        $ig = $op['instagram_business_account'] ?? $op['connected_instagram_account'] ?? null;
                        if (! empty($ig['id'])) {
                            $this->persistDiscoveredAccount($account, (string) $ig['id'], $ig['name'] ?? null, $ig['username'] ?? null);
                            return [
                                'account_id' => (string) $ig['id'],
                                'token' => $token,
                                'base_url' => 'https://graph.facebook.com/v19.0',
                                'diagnostics' => $diagnostics,
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Continue
        }

        return [
            'account_id' => $currentId,
            'token' => $token,
            'base_url' => 'https://graph.facebook.com/v19.0',
            'diagnostics' => $diagnostics,
        ];
    }

    protected function persistDiscoveredAccount(SocialAccount $account, string $realId, ?string $name = null, ?string $username = null): void
    {
        if (empty($realId)) {
            return;
        }

        $updates = [];
        if ($account->account_id !== $realId) {
            $updates['account_id'] = $realId;
        }
        if ($name && (empty($account->name) || str_contains($account->name, 'Hesabım') || str_contains($account->name, 'Resmi'))) {
            $updates['name'] = $name;
        }
        if ($username && (empty($account->username) || str_contains($account->username, '_official') || str_contains($account->username, 'instagram_'))) {
            $updates['username'] = ltrim($username, '@');
        }

        if (! empty($updates)) {
            $account->update($updates);
        }
    }

    /**
     * Fallback: Upload video binary directly to Meta rupload server (bypasses public URL fetch issues)
     */
    protected function tryResumableUpload(Video $video, string $igAccountId, string $token, string $caption, string $baseUrl): ?string
    {
        try {
            if (! $video->storage_path) {
                return null;
            }

            $videoFilePath = Storage::disk($video->disk ?: 'public')->path($video->storage_path);
            if (! file_exists($videoFilePath)) {
                return null;
            }

            $fileSize = filesize($videoFilePath);
            if (! $fileSize || $fileSize <= 0) {
                return null;
            }

            $initResp = Http::withToken($token)
                ->timeout(30)
                ->post("{$baseUrl}/{$igAccountId}/media", [
                    'media_type' => 'REELS',
                    'upload_type' => 'resumable',
                    'caption' => $caption,
                    'share_to_feed' => true,
                ]);

            if (! $initResp->successful() || ! $initResp->json('id')) {
                return null;
            }

            $containerId = (string) $initResp->json('id');
            $uploadUri = $initResp->json('uri') ?: "https://rupload.facebook.com/ig-api-upload/v19.0/{$containerId}";

            $stream = fopen($videoFilePath, 'rb');
            $uploadResp = Http::withHeaders([
                'Authorization' => "OAuth {$token}",
                'offset' => '0',
                'file_size' => (string) $fileSize,
            ])
                ->timeout(180)
                ->withBody($stream, 'application/octet-stream')
                ->post($uploadUri);

            if (is_resource($stream)) {
                fclose($stream);
            }

            return $uploadResp->successful() ? $containerId : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build a clear, actionable Turkish error message instead of raw Graph API JSON
     */
    protected function buildFriendlyErrorMessage(mixed $rawError, string $attemptedId, string $token, array $diagnostics): string
    {
        $errorArr = is_array($rawError) ? ($rawError['error'] ?? $rawError) : [];
        $code = (int) ($errorArr['code'] ?? 0);
        $subcode = (int) ($errorArr['error_subcode'] ?? 0);
        $message = (string) ($errorArr['message'] ?? (is_string($rawError) ? $rawError : json_encode($rawError)));

        if ($code === 100 && $subcode === 33) {
            $scopes = $diagnostics['scopes'] ?? [];
            $pagesFound = (int) ($diagnostics['pages_found'] ?? 0);
            $appId = $diagnostics['app_id'] ?? '';
            $userId = $diagnostics['user_id'] ?? '';

            $missingScopes = array_diff(['instagram_basic', 'instagram_content_publish', 'pages_show_list'], $scopes);

            if (! empty($scopes) && ! empty($missingScopes)) {
                return "Instagram Yetki Eksikliği: Kullandığınız Meta Access Token içinde '" . implode(', ', $missingScopes) . "' izinleri eksik. Lütfen Meta Graph API Explorer'da sağ panelden 'instagram_basic', 'instagram_content_publish', 'pages_show_list' ve 'business_management' izinlerini seçip 'Generate Access Token' butonuna basarak yeni token alın.";
            }

            if ($attemptedId === $appId || $attemptedId === $userId || ! str_starts_with($attemptedId, '178414')) {
                if ($pagesFound === 0) {
                    return "Instagram Hesap ID Hatası: Girdiğiniz ID ({$attemptedId}) bir Instagram İşletme ID'si (178414...) değil, Facebook Kullanıcı/Uygulama ID'sidir ve Token içinde bağlı bir Facebook Sayfası / Instagram Profesyonel hesabı bulunamadı. Çözüm: Meta Graph API Explorer'da izinlere 'business_management', 'pages_show_list', 'instagram_basic', 'instagram_content_publish' ekleyip 'Generate Access Token' yaparken açılan pencerede Facebook Sayfanızı ve bağlı Instagram İşletme hesabınızı seçin.";
                }

                return "Instagram Bağlantı Hatası: Facebook Sayfanız bulundu ancak bu sayfaya bağlı bir Instagram Profesyonel/İşletme (Business) hesabı tespit edilemedi (Mevcut ID: {$attemptedId}). Lütfen Instagram mobil uygulamasından veya Facebook Sayfa Ayarları > Bağlı Hesaplar menüsünden Instagram hesabınızı Facebook Sayfanıza bağlayın.";
            }
        }

        if ($code === 190) {
            return 'Instagram Erişim Anahtarı (Access Token) süresi dolmuş veya geçersiz. Lütfen Hesaplar & Yetkilendirme sayfasından yeni bir Access Token girin.';
        }

        return "Instagram Media Container Hatası: {$message}";
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
        if (! $clientId || ! $clientSecret) {
            return false;
        }

        $response = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'fb_exchange_token' => $credential->access_token,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            return false;
        }

        $credential->update([
            'access_token' => $response->json('access_token'),
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

        $resolved = $this->resolveInstagramAccount($account, $token);
        $igId = $resolved['account_id'];
        $baseUrl = $resolved['base_url'];

        $response = Http::withToken($resolved['token'])
            ->get("{$baseUrl}/{$igId}", [
                'fields' => 'id,username',
            ]);

        return $response->successful();
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        if ($this->isSandboxToken($token)) {
            return ['views' => 2310, 'likes' => 310, 'comments' => 42, 'shares' => 65];
        }

        $response = Http::withToken($token)
            ->get("https://graph.facebook.com/v19.0/{$externalPostId}", [
                'fields' => 'like_count,comments_count',
            ]);

        return [
            'views' => 0,
            'likes' => (int) $response->json('like_count', 0),
            'comments' => (int) $response->json('comments_count', 0),
            'shares' => 0,
        ];
    }
}
