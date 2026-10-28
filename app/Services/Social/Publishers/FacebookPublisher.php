<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FacebookPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'facebook';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        @set_time_limit(180);

        $token = $this->getAccessToken($account);
        $caption = $this->buildFullCaption($target);
        $videoUrl = $video->url;

        // Otomasyon kuralında / hedefte belirlenen format (Öncelikli) veya kanal ayarı
        $isTextOnly = $target->post_type === 'text'
            || $target->scheduledPost?->post_type === 'text'
            || (Setting::get('partner_facebook_post_mode') === 'text');

        if ($this->isSandboxToken($token)) {
            $postId = 'fb_post_' . uniqid();
            $url = $isTextOnly
                ? "https://facebook.com/{$account->account_id}/posts/" . rand(1000000, 9999999)
                : "https://facebook.com/{$account->account_id}/videos/" . rand(1000000, 9999999);

            return [
                'post_id' => $postId,
                'post_url' => $url,
                'status' => 'published',
                'is_mock' => true,
                'post_type' => $isTextOnly ? 'text' : 'video',
            ];
        }

        // Automatically resolve true Facebook Page ID and Page Access Token
        $resolved = $this->resolveFacebookPage($account, $token);
        $pageId = $resolved['page_id'];
        $pageToken = $resolved['page_token'];

        if ($isTextOnly) {
            // Metin Gönderisi Modu: Facebook Sayfası Feed'ine videosuz ve emojissiz metin gönderisi paylaşır
            $cleanCaption = \App\Services\AI\OpenAiContentService::stripEmojis($caption);
            $endpoint = "https://graph.facebook.com/v19.0/{$pageId}/feed";
            $response = Http::withToken($pageToken)
                ->timeout(60)
                ->post($endpoint, [
                    'message' => $cleanCaption,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException($this->buildFriendlyFacebookError($response->json() ?: $response->body(), $pageId));
            }

            $result = $response->json();
            $postId = $result['id'] ?? null;

            return [
                'post_id' => $postId,
                'post_url' => "https://facebook.com/{$postId}",
                'status' => 'published',
                'post_type' => 'text',
                'raw' => $result,
            ];
        }

        $endpoint = "https://graph.facebook.com/v19.0/{$pageId}/videos";
        $response = Http::withToken($pageToken)
            ->timeout(120)
            ->post($endpoint, [
                'description' => $caption,
                'title' => $target->title ?: $video->title,
                'file_url' => $videoUrl,
            ]);

        // Fallback: Upload binary file directly from local disk if file_url fails or is unreachable
        if (! $response->successful() && $video->storage_path) {
            $videoFilePath = Storage::disk($video->disk ?: 'public')->path($video->storage_path);
            if (file_exists($videoFilePath)) {
                $stream = fopen($videoFilePath, 'rb');
                $response = Http::withToken($pageToken)
                    ->timeout(180)
                    ->attach('source', $stream, basename($videoFilePath))
                    ->post($endpoint, [
                        'description' => $caption,
                        'title' => $target->title ?: $video->title,
                    ]);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        if (! $response->successful()) {
            throw new \RuntimeException($this->buildFriendlyFacebookError($response->json() ?: $response->body(), $pageId));
        }

        $result = $response->json();
        $videoId = $result['id'] ?? null;

        return [
            'post_id' => $videoId,
            'post_url' => "https://facebook.com/{$pageId}/videos/{$videoId}",
            'status' => 'published',
            'post_type' => 'video',
            'raw' => $result,
        ];
    }

    /**
     * Automatically discover Facebook Page ID and Page Access Token
     */
    public function resolveFacebookPage(SocialAccount $account, string $token): array
    {
        $currentId = trim((string) $account->account_id);

        // 1. Check if currentId is already a Facebook Page and retrieve its Page Access Token
        if (ctype_digit($currentId)) {
            try {
                $directResp = Http::withToken($token)
                    ->timeout(10)
                    ->get("https://graph.facebook.com/v19.0/{$currentId}", [
                        'fields' => 'id,name,username,access_token,category',
                    ]);

                if ($directResp->successful() && ($directResp->json('category') || $directResp->json('access_token'))) {
                    return [
                        'page_id' => $currentId,
                        'page_token' => $directResp->json('access_token') ?: $token,
                    ];
                }
            } catch (\Throwable) {
                // Proceed to discovery
            }
        }

        // 2. Check /me/accounts for managed Pages
        try {
            $pagesResp = Http::withToken($token)
                ->timeout(15)
                ->get('https://graph.facebook.com/v19.0/me/accounts', [
                    'fields' => 'id,name,username,access_token,category',
                ]);

            if ($pagesResp->successful()) {
                $pages = $pagesResp->json('data', []);
                if (! empty($pages[0]['id'])) {
                    $page = $pages[0];
                    $this->persistDiscoveredPage($account, (string) $page['id'], $page['name'] ?? null, $page['username'] ?? null);
                    return [
                        'page_id' => (string) $page['id'],
                        'page_token' => $page['access_token'] ?: $token,
                    ];
                }
            }
        } catch (\Throwable) {
            // Continue discovery
        }

        // 3. Check /debug_token granular_scopes for Page target_ids
        try {
            $debugResp = Http::timeout(15)->get('https://graph.facebook.com/v19.0/debug_token', [
                'input_token' => $token,
                'access_token' => $token,
            ]);

            if ($debugResp->successful()) {
                foreach ($debugResp->json('data.granular_scopes', []) as $gs) {
                    $scopeName = $gs['scope'] ?? '';
                    $targetIds = $gs['target_ids'] ?? [];
                    if (in_array($scopeName, ['pages_manage_posts', 'pages_show_list', 'pages_read_engagement'], true) && ! empty($targetIds)) {
                        $candidatePageId = (string) $targetIds[0];
                        $pageInfo = Http::withToken($token)
                            ->timeout(10)
                            ->get("https://graph.facebook.com/v19.0/{$candidatePageId}", [
                                'fields' => 'id,name,username,access_token',
                            ]);

                        if ($pageInfo->successful() && $pageInfo->json('id')) {
                            $this->persistDiscoveredPage($account, $candidatePageId, $pageInfo->json('name'), $pageInfo->json('username'));
                            return [
                                'page_id' => $candidatePageId,
                                'page_token' => $pageInfo->json('access_token') ?: $token,
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Continue
        }

        // 4. Check if /me itself is a Page Access Token
        try {
            $meResp = Http::withToken($token)
                ->timeout(10)
                ->get('https://graph.facebook.com/v19.0/me', [
                    'fields' => 'id,name,username,category',
                ]);

            if ($meResp->successful() && $meResp->json('category') && $meResp->json('id')) {
                $realPageId = (string) $meResp->json('id');
                $this->persistDiscoveredPage($account, $realPageId, $meResp->json('name'), $meResp->json('username'));
                return [
                    'page_id' => $realPageId,
                    'page_token' => $token,
                ];
            }
        } catch (\Throwable) {
            // Continue
        }

        return [
            'page_id' => $currentId,
            'page_token' => $token,
        ];
    }

    protected function persistDiscoveredPage(SocialAccount $account, string $pageId, ?string $name = null, ?string $username = null): void
    {
        if (empty($pageId)) {
            return;
        }

        $updates = [];
        if ($account->account_id !== $pageId) {
            $updates['account_id'] = $pageId;
        }
        if ($name && (empty($account->name) || str_contains($account->name, 'Hesabım') || str_contains($account->name, 'Resmi'))) {
            $updates['name'] = $name;
        }
        if ($username && (empty($account->username) || str_contains($account->username, '_official'))) {
            $updates['username'] = ltrim($username, '@');
        }

        if (! empty($updates)) {
            $account->update($updates);
        }
    }

    protected function buildFriendlyFacebookError(mixed $rawError, string $attemptedId): string
    {
        $errorArr = is_array($rawError) ? ($rawError['error'] ?? $rawError) : [];
        $code = (int) ($errorArr['code'] ?? 0);
        $subcode = (int) ($errorArr['error_subcode'] ?? 0);
        $message = (string) ($errorArr['message'] ?? (is_string($rawError) ? $rawError : json_encode($rawError)));

        if ($code === 100 && $subcode === 33) {
            return "Facebook Sayfa ID Hatası: Girdiğiniz ID ({$attemptedId}) bir Facebook Sayfası (Page ID) değil, Uygulama veya Kişisel Kullanıcı ID'sidir ve Token içinde yönetilen bir Facebook Sayfası bulunamadı. Lütfen Meta Graph API Explorer'da 'User or Page' menüsünden 'Get Page Access Token' seçip 'pages_manage_posts', 'pages_read_engagement', 'pages_show_list' ve 'business_management' izinleriyle yeni token alın.";
        }

        if ($code === 200) {
            return "Facebook Yayın Yetkisi Eksik: Token'ınızda 'pages_manage_posts' (Sayfa Gönderisi Yönetme) izni bulunmuyor veya Kişisel Token yerine Sayfa Erişim Anahtarı (Page Access Token) kullanmanız gerekiyor.";
        }

        return "Facebook Graph API Hatası: {$message}";
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

        $resolved = $this->resolveFacebookPage($account, $token);
        $response = Http::withToken($resolved['page_token'])
            ->get("https://graph.facebook.com/v19.0/{$resolved['page_id']}", [
                'fields' => 'id,name',
            ]);

        return $response->successful();
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        if ($this->isSandboxToken($token)) {
            return ['views' => 980, 'likes' => 112, 'comments' => 19, 'shares' => 27];
        }

        $response = Http::withToken($token)
            ->get("https://graph.facebook.com/v19.0/{$externalPostId}", [
                'fields' => 'views,likes.summary(true),comments.summary(true)',
            ]);

        return [
            'views' => (int) $response->json('views', 0),
            'likes' => (int) $response->json('likes.summary.total_count', 0),
            'comments' => (int) $response->json('comments.summary.total_count', 0),
            'shares' => 0,
        ];
    }
}
