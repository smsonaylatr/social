<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PinterestPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'pinterest';
    }

    protected function httpClient(string $token, int $timeout = 30): PendingRequest
    {
        return Http::withToken($token)
            ->timeout($timeout)
            ->connectTimeout(10);
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        @set_time_limit(240);

        $token = $this->getAccessToken($account);

        if ($this->isSandboxToken($token)) {
            $pinId = 'pin_' . uniqid();
            return [
                'post_id' => $pinId,
                'post_url' => "https://pinterest.com/pin/{$pinId}/",
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        $videoFilePath = $this->resolveLocalVideoPath($video);
        if (! $videoFilePath) {
            throw new \RuntimeException('Yüklenecek video dosyası sunucu diskinde bulunamadı: ' . ($video->storage_path ?: 'boş yol'));
        }

        $authMode = $account->credential?->extra_data['pinterest_env'] ?? null;
        $preferSandbox = ($authMode === 'sandbox') || (Setting::get('partner_pinterest_trial_sandbox') === '1');

        $environments = $preferSandbox
            ? ['https://api-sandbox.pinterest.com/v5', 'https://api.pinterest.com/v5']
            : ['https://api.pinterest.com/v5', 'https://api-sandbox.pinterest.com/v5'];

        $lastException = null;

        foreach ($environments as $index => $baseUrl) {
            try {
                $envToken = $this->ensureTokenForEnvironment($account, $token, $baseUrl);
                return $this->attemptPublishOnEnvironment($video, $videoFilePath, $target, $account, $envToken, $baseUrl);
            } catch (\Throwable $e) {
                $msgLower = strtolower($e->getMessage());

                // If production failed due to Trial Access restriction, remember sandbox preference and try sandbox
                if (str_contains($baseUrl, 'api.pinterest.com') && (str_contains($msgLower, 'trial') || str_contains($msgLower, '403') || str_contains($msgLower, 'code":29'))) {
                    Setting::set('partner_pinterest_trial_sandbox', '1', 'partner_oauth');
                }

                // Keep the primary error if fallback fails with 401 token mismatch
                if ($lastException !== null && str_contains($msgLower, '401') && str_contains(strtolower($lastException->getMessage()), 'trial')) {
                    throw new \RuntimeException(
                        'Pinterest Developer uygulamanız şu anda "Trial (Deneme)" modunda olduğu için canlı Pinterest API üzerinden Pin oluşturmaya izin vermiyor (Sandbox API gerekiyor). '
                        . 'Sosyal Medya Kanalları > Pinterest sayfasından "İzinleri Yenile / Yeniden Bağla" butonuna tıklayarak hesabınızı yeniden bağlayın (sistem otomatik olarak Pinterest Sandbox Token alacaktır).'
                    );
                }

                $lastException = $e;

                // Only try the second environment if the first failed with 401/403/Trial error
                if ($index === 0 && (
                    str_contains($msgLower, '401')
                    || str_contains($msgLower, '403')
                    || str_contains($msgLower, 'trial')
                    || str_contains($msgLower, 'not permitted')
                )) {
                    continue;
                }

                break;
            }
        }

        throw $lastException ?? new \RuntimeException('Pinterest paylaşımı sırasında bilinmeyen bir hata oluştu.');
    }

    /**
     * Execute full media registration -> S3 upload -> media status poll -> Pin creation on the given Pinterest API v5 base URL
     */
    protected function attemptPublishOnEnvironment(
        Video $video,
        string $videoFilePath,
        ScheduledPostTarget $target,
        SocialAccount $account,
        string $token,
        string $baseUrl
    ): array {
        // 1. Resolve a valid numeric Pinterest Board ID (auto-discover or auto-create if missing)
        $boardId = $this->resolveBoardId($account, $token, $baseUrl);

        // 2. Register video media upload on Pinterest API v5
        $regResponse = $this->httpClient($token, 30)
            ->post("{$baseUrl}/media", [
                'media_type' => 'video',
            ]);

        if (! $regResponse->successful()) {
            throw new \RuntimeException($this->buildFriendlyPinterestError('Media Register', $regResponse->status(), $regResponse->json() ?: $regResponse->body()));
        }

        $mediaId = (string) $regResponse->json('media_id');
        $uploadUrl = (string) $regResponse->json('upload_url');
        $uploadParams = $regResponse->json('upload_parameters', []);

        if (! $mediaId || ! $uploadUrl) {
            throw new \RuntimeException('Pinterest Media Register yanıtında media_id veya upload_url bulunamadı: ' . $regResponse->body());
        }

        // 3. Upload MP4 file to Pinterest S3 presigned URL
        $uploadRequest = Http::timeout(180)->connectTimeout(15)->asMultipart();
        foreach ($uploadParams as $k => $v) {
            $uploadRequest->attach((string) $k, (string) $v);
        }

        $stream = fopen($videoFilePath, 'rb');
        $uploadResponse = $uploadRequest
            ->attach('file', $stream, 'video.mp4', ['Content-Type' => $video->mime_type ?: 'video/mp4'])
            ->post($uploadUrl);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! $uploadResponse->successful() && $uploadResponse->status() !== 204) {
            throw new \RuntimeException('Pinterest S3 Video Yükleme Hatası (' . $uploadResponse->status() . '): ' . mb_substr($uploadResponse->body(), 0, 300));
        }

        // 4. Poll GET /v5/media/{media_id} until Pinterest finishes processing the video
        $this->waitForMediaProcessing($baseUrl, $token, $mediaId);

        // 5. Build Pin payload
        $rawTitle = trim((string) ($target->title ?: $video->title));
        if ($rawTitle === '' || preg_match('/^\d+[\.\s\-]+video$/i', $rawTitle)) {
            $rawTitle = 'Patenli Ayakkabı & Tekerlekli Spor Ayakkabı Modelleri';
        }
        $title = mb_substr($rawTitle, 0, 100, 'UTF-8');
        $description = $this->safeTruncate($this->buildFullCaption($target), 500);
        $link = $target->product_url ?: ($video->product_url ?: 'https://patenliayakkabilar.com');

        // Use cover_image_key_frame_time (1s) so Pinterest extracts cover directly from video without failing on .svg placeholder
        $mediaSource = [
            'source_type' => 'video_id',
            'media_id' => $mediaId,
            'cover_image_key_frame_time' => 1,
        ];

        $customThumbUrl = $this->resolveValidCoverImageUrl($video);
        if ($customThumbUrl) {
            $mediaSource = [
                'source_type' => 'video_id',
                'media_id' => $mediaId,
                'cover_image_url' => $customThumbUrl,
            ];
        }

        $pinPayload = [
            'board_id' => (string) $boardId,
            'title' => $title,
            'description' => $description,
            'link' => $link,
            'media_source' => $mediaSource,
        ];

        // 6. Create Pin (with retry if video transcoding is finalizing or if cover_image_url needs key_frame fallback)
        $pinResponse = null;
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $pinResponse = $this->httpClient($token, 30)
                ->post("{$baseUrl}/pins", $pinPayload);

            if ($pinResponse->successful()) {
                break;
            }

            // If cover_image_url failed, switch to cover_image_key_frame_time=1 immediately
            if (isset($pinPayload['media_source']['cover_image_url'])) {
                unset($pinPayload['media_source']['cover_image_url']);
                $pinPayload['media_source']['cover_image_key_frame_time'] = 1;
            }

            $bodyLower = strtolower($pinResponse->body());
            if ($attempt < 5 && (
                $pinResponse->status() === 400
                || str_contains($bodyLower, 'media')
                || str_contains($bodyLower, 'process')
                || str_contains($bodyLower, 'ready')
            )) {
                sleep(3);
                continue;
            }

            break;
        }

        if (! $pinResponse || ! $pinResponse->successful()) {
            $status = $pinResponse ? $pinResponse->status() : 500;
            $body = $pinResponse ? ($pinResponse->json() ?: $pinResponse->body()) : 'Yanıt alınamadı';
            throw new \RuntimeException($this->buildFriendlyPinterestError('Pin Oluşturma', $status, $body));
        }

        $pinId = $pinResponse->json('id');

        return [
            'post_id' => $pinId,
            'post_url' => "https://pinterest.com/pin/{$pinId}/",
            'status' => 'published',
            'post_type' => 'video',
        ];
    }

    /**
     * Ensure token is valid on the target baseUrl, attempting refresh on that environment if 401 occurs
     */
    protected function ensureTokenForEnvironment(SocialAccount $account, string $token, string $baseUrl): string
    {
        try {
            $check = $this->httpClient($token, 12)->get("{$baseUrl}/boards", ['page_size' => 1]);
            if ($check->successful()) {
                return $token;
            }

            if ($check->status() === 401) {
                $credential = $account->credential;
                $clientId = $this->getClientId();
                $clientSecret = $this->getClientSecret();

                if ($credential && $credential->refresh_token && $clientId && $clientSecret) {
                    $refreshResp = Http::withBasicAuth($clientId, $clientSecret)
                        ->timeout(15)
                        ->asForm()
                        ->post("{$baseUrl}/oauth/token", [
                            'grant_type' => 'refresh_token',
                            'refresh_token' => $credential->refresh_token,
                        ]);

                    if ($refreshResp->successful() && $refreshResp->json('access_token')) {
                        $newToken = (string) $refreshResp->json('access_token');
                        $credential->update([
                            'access_token' => $newToken,
                            'refresh_token' => $refreshResp->json('refresh_token', $credential->refresh_token),
                            'token_expires_at' => now()->addSeconds((int) $refreshResp->json('expires_in', 2592000)),
                        ]);
                        return $newToken;
                    }
                }
            }
        } catch (\Throwable) {
            // Ignore and return original token
        }

        return $token;
    }

    /**
     * Resolve a valid numeric Pinterest Board ID from the user's account, or auto-create a board if none exist
     */
    protected function resolveBoardId(SocialAccount $account, string $token, string $baseUrl): string
    {
        $currentId = trim((string) $account->account_id);

        try {
            $boardsResp = $this->httpClient($token, 15)->get("{$baseUrl}/boards", [
                'page_size' => 25,
            ]);

            if ($boardsResp->successful()) {
                $boards = $boardsResp->json('items', []);

                // 1. If account_id is already a valid board ID in user's boards, keep it
                foreach ($boards as $board) {
                    if (! empty($board['id']) && (string) $board['id'] === $currentId) {
                        return $currentId;
                    }
                }

                // 2. Prefer a public board if available, otherwise first board
                $selectedBoard = collect($boards)->first(fn ($b) => ($b['privacy'] ?? 'PUBLIC') === 'PUBLIC')
                    ?? ($boards[0] ?? null);

                if (! empty($selectedBoard['id'])) {
                    $boardId = (string) $selectedBoard['id'];
                    $account->update(['account_id' => $boardId]);
                    return $boardId;
                }

                // 3. User has 0 boards on Pinterest -> attempt to create a default board automatically
                $createResp = $this->httpClient($token, 15)->post("{$baseUrl}/boards", [
                    'name' => 'Patenli Ayakkabılar',
                    'description' => 'Patenli spor ayakkabı ve tekerlekli ayakkabı modelleri',
                    'privacy' => 'PUBLIC',
                ]);

                if ($createResp->successful() && $createResp->json('id')) {
                    $newBoardId = (string) $createResp->json('id');
                    $account->update(['account_id' => $newBoardId]);
                    return $newBoardId;
                }

                throw new \RuntimeException(
                    'Pinterest hesabınızda paylaşım yapılacak hiç Pano (Board) bulunamadı. '
                    . 'Lütfen Pinterest hesabınıza girip en az 1 adet herkese açık Pano (Board) oluşturun veya Sosyal Medya Kanalları > Pinterest sayfasından izinleri yenileyin.'
                );
            }

            if (in_array($boardsResp->status(), [401, 403], true)) {
                throw new \RuntimeException($this->buildFriendlyPinterestError('Pano Listeleme', $boardsResp->status(), $boardsResp->json() ?: $boardsResp->body()));
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Pinterest board resolution warning: ' . $e->getMessage());
        }

        if (ctype_digit($currentId)) {
            return $currentId;
        }

        throw new \RuntimeException(
            'Pinterest Pano Kimliği (board_id) doğrulanamadı (' . $currentId . '). '
            . 'Lütfen Pinterest hesabınızda en az 1 adet Pano (Board) olduğundan emin olun veya Sosyal Medya Kanalları sayfasından Pinterest bağlantısını yenileyin.'
        );
    }

    /**
     * Poll GET /v5/media/{media_id} until video status is 'succeeded'
     */
    protected function waitForMediaProcessing(string $baseUrl, string $token, string $mediaId): void
    {
        for ($i = 0; $i < 20; $i++) {
            sleep(2);

            try {
                $statusResp = $this->httpClient($token, 15)->get("{$baseUrl}/media/{$mediaId}");
                if (! $statusResp->successful()) {
                    continue;
                }

                $status = strtolower((string) $statusResp->json('status'));
                if ($status === 'succeeded') {
                    return;
                }

                if ($status === 'failed') {
                    throw new \RuntimeException('Pinterest yüklenen video dosyasını işleyemedi (media status: failed).');
                }
            } catch (\RuntimeException $e) {
                throw $e;
            } catch (\Throwable) {
                // Continue polling
            }
        }
    }

    /**
     * Return a public HTTPS JPG/PNG thumbnail URL if one exists on disk, or null (never return .svg placeholder)
     */
    protected function resolveValidCoverImageUrl(Video $video): ?string
    {
        if (! $video->thumbnail_path) {
            return null;
        }

        $ext = strtolower(pathinfo($video->thumbnail_path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        if (! Storage::disk($video->disk ?? 'public')->exists($video->thumbnail_path)) {
            return null;
        }

        $url = asset('storage/' . ltrim($video->thumbnail_path, '/'));
        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            return null;
        }

        return preg_replace('/^http:\/\//i', 'https://', $url);
    }

    protected function resolveLocalVideoPath(Video $video): ?string
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
                return $path;
            }
        }

        return null;
    }

    protected function buildFriendlyPinterestError(string $step, int $status, mixed $rawError): string
    {
        $code = is_array($rawError) ? ($rawError['code'] ?? null) : null;
        $message = is_array($rawError)
            ? ($rawError['message'] ?? json_encode($rawError, JSON_UNESCAPED_UNICODE))
            : (string) $rawError;

        if ($status === 401) {
            return "Pinterest Yetkilendirme Hatası (401): Erişim anahtarı geçersiz veya süresi dolmuş. Lütfen Sosyal Medya Kanalları > Pinterest sayfasından hesabı yeniden bağlayın. ({$message})";
        }

        if ($status === 403 || $code === 29) {
            if (str_contains(strtolower($message), 'trial')) {
                return "Pinterest Developer 'Trial Access' Kısıtlaması (403): Pinterest Developer Portal'daki uygulamanız henüz 'Trial (Deneme)' modunda olduğu için canlı API yazma isteğini reddetti. Detay: {$message}";
            }
            return "Pinterest Yetki Hatası (403 - {$step}): Uygulamanızın 'pins:write' ve 'boards:read' izinlerine sahip olduğundan emin olun. Detay: {$message}";
        }

        return "Pinterest {$step} Hatası ({$status}): {$message}";
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

        foreach (['https://api.pinterest.com/v5/oauth/token', 'https://api-sandbox.pinterest.com/v5/oauth/token'] as $tokenEndpoint) {
            try {
                $response = Http::withBasicAuth($clientId, $clientSecret)
                    ->timeout(15)
                    ->asForm()
                    ->post($tokenEndpoint, [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $credential->refresh_token,
                    ]);

                if ($response->successful() && $response->json('access_token')) {
                    $credential->update([
                        'access_token' => $response->json('access_token'),
                        'refresh_token' => $response->json('refresh_token', $credential->refresh_token),
                        'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 2592000)),
                    ]);

                    return true;
                }
            } catch (\Throwable) {
                // Try next endpoint
            }
        }

        return false;
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

        foreach (['https://api.pinterest.com/v5', 'https://api-sandbox.pinterest.com/v5'] as $baseUrl) {
            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$baseUrl}/boards", ['page_size' => 1]);

            if ($response->successful()) {
                return true;
            }
        }

        return false;
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        if ($this->isSandboxToken($token)) {
            return ['views' => 620, 'likes' => 75, 'comments' => 9, 'shares' => 28];
        }

        $response = Http::withToken($token)
            ->timeout(15)
            ->get("https://api.pinterest.com/v5/pins/{$externalPostId}/analytics", [
                'start_date' => now()->subDays(30)->toDateString(),
                'end_date' => now()->toDateString(),
                'metric_types' => 'IMPRESSION,SAVE,PIN_CLICK',
            ]);

        $metrics = $response->json('all.summary_metrics', []);

        return [
            'views' => (int) ($metrics['IMPRESSION'] ?? 0),
            'likes' => (int) ($metrics['SAVE'] ?? 0),
            'comments' => 0,
            'shares' => (int) ($metrics['PIN_CLICK'] ?? 0),
        ];
    }
}
