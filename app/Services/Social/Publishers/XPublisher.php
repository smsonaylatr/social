<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class XPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'x';
    }

    protected function httpClient(string $token, int $timeout = 30): PendingRequest
    {
        return Http::withToken($token)
            ->timeout($timeout)
            ->connectTimeout(10)
            ->withOptions([
                'force_ip_resolve' => 'v4',
            ]);
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        @set_time_limit(180);

        $token = $this->getAccessToken($account);
        $fullText = $this->buildFullCaption($target);

        // Ensure full https:// URL for X so X always generates the SEO Website Link Card ("From patenliayakkabilar.com")
        if (! str_contains($fullText, 'https://') && ! str_contains($fullText, 'http://')) {
            $fullText = preg_replace('/\b(patenliayakkabilar\.com)\b/i', 'https://$1', $fullText);
        }

        $caption = mb_substr($fullText, 0, 280);

        if ($this->isSandboxToken($token)) {
            $tweetId = '178' . rand(10000000000000, 99999999999999);
            return [
                'post_id' => $tweetId,
                'post_url' => "https://x.com/{$account->username}/status/{$tweetId}",
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        // 1. Upload video media (supports X v2 media/upload and v1.1 fallback)
        $mediaId = $this->tryUploadVideoMedia($video, $token, $account);

        // 2. Create Tweet: POST /2/tweets (with api.x.com and api.twitter.com fallback)
        $account->load('credential');
        if ($account->credential?->access_token) {
            $token = $account->credential->access_token;
        }

        $payload = [
            'text' => $caption,
        ];

        if ($mediaId) {
            $payload['media'] = [
                'media_ids' => [(string) $mediaId],
            ];
        }

        $tweetResponse = $this->postTweetRequest($token, $payload);

        // If 401 Unauthorized, refresh the 2-hour OAuth 2.0 token and retry
        if ($tweetResponse && $tweetResponse->status() === 401 && $this->refreshToken($account)) {
            $account->load('credential');
            $token = $account->credential->access_token;
            $tweetResponse = $this->postTweetRequest($token, $payload);
        }

        // If video is still finishing transcoding on X when tweet was posted, wait 5s and retry WITH video
        if ($tweetResponse && ! $tweetResponse->successful() && isset($payload['media'])) {
            sleep(5);
            $tweetResponse = $this->postTweetRequest($token, $payload);
        }

        // If 403 duplicate content error (when testing the same video multiple times), make text unique
        if ($tweetResponse && $tweetResponse->status() === 403 && str_contains(strtolower($tweetResponse->body()), 'duplicate')) {
            $suffix = ' ✨';
            $payload['text'] = mb_substr($caption, 0, 280 - mb_strlen($suffix)) . $suffix;
            $tweetResponse = $this->postTweetRequest($token, $payload);
        }

        if (! $tweetResponse || ! $tweetResponse->successful()) {
            $status = $tweetResponse ? $tweetResponse->status() : 500;
            $body = $tweetResponse ? ($tweetResponse->json() ?: $tweetResponse->body()) : 'X API sunucusuna bağlanılamadı.';
            throw new \RuntimeException($this->buildFriendlyXError($status, $body));
        }

        $tweetId = $tweetResponse->json('data.id');
        $username = $account->username ?: 'i/web';

        return [
            'post_id' => $tweetId,
            'post_url' => "https://x.com/{$username}/status/{$tweetId}",
            'status' => 'published',
        ];
    }

    protected function postTweetRequest(string $token, array $payload)
    {
        foreach (['https://api.x.com/2/tweets', 'https://api.twitter.com/2/tweets'] as $endpoint) {
            try {
                return $this->httpClient($token, 25)->post($endpoint, $payload);
            } catch (\Throwable $e) {
                $lastError = $e;
            }
        }

        throw new \RuntimeException('X API Bağlantı Hatası: ' . ($lastError?->getMessage() ?? 'Zaman aşımı'));
    }

    /**
     * Attempt chunked video upload via X API v2 (/2/media/upload/initialize -> append -> finalize -> status)
     * with fallback to v1.1 (upload.twitter.com/1.1/media/upload.json).
     */
    protected function tryUploadVideoMedia(Video $video, string $token, ?SocialAccount $account = null): ?string
    {
        $videoFilePath = $this->resolveLocalVideoPath($video);
        if (! $videoFilePath) {
            return null;
        }

        $fileSize = filesize($videoFilePath);
        if (! $fileSize || $fileSize <= 0) {
            return null;
        }

        $mimeType = $video->mime_type ?: 'video/mp4';
        $v2AuthError = null;

        // 1. Primary: X API v2 Chunked Media Upload (supports OAuth 2.0 PKCE User Token with media.write scope)
        try {
            $v2Init = $this->httpClient($token, 20)
                ->post('https://api.x.com/2/media/upload/initialize', [
                    'total_bytes' => (int) $fileSize,
                    'media_type' => $mimeType,
                    'media_category' => 'tweet_video',
                ]);

            if ($v2Init->status() === 401 && $account && $this->refreshToken($account)) {
                $account->load('credential');
                $token = $account->credential->access_token;
                $v2Init = $this->httpClient($token, 20)
                    ->post('https://api.x.com/2/media/upload/initialize', [
                        'total_bytes' => (int) $fileSize,
                        'media_type' => $mimeType,
                        'media_category' => 'tweet_video',
                    ]);
            }

            if (in_array($v2Init->status(), [401, 403], true)) {
                $v2AuthError = $v2Init->body();
            }

            $v2MediaId = $v2Init->json('data.id') ?? $v2Init->json('media_id_string');
            if ($v2Init->successful() && $v2MediaId) {
                $mediaId = (string) $v2MediaId;
                $fileHandle = fopen($videoFilePath, 'rb');
                $segmentIndex = 0;
                $appendOk = true;

                while (! feof($fileHandle)) {
                    $chunk = fread($fileHandle, 4 * 1024 * 1024);
                    $appendResp = $this->httpClient($token, 60)
                        ->attach('media', $chunk, 'video.mp4')
                        ->post("https://api.x.com/2/media/upload/{$mediaId}/append", [
                            'segment_index' => $segmentIndex,
                        ]);

                    if (! $appendResp->successful()) {
                        $appendOk = false;
                        break;
                    }
                    $segmentIndex++;
                }
                fclose($fileHandle);

                if ($appendOk) {
                    $finalizeResp = $this->httpClient($token, 25)
                        ->send('POST', "https://api.x.com/2/media/upload/{$mediaId}/finalize");

                    if (! $finalizeResp->successful()) {
                        $finalizeResp = $this->httpClient($token, 25)
                            ->withBody('{}', 'application/json')
                            ->post("https://api.x.com/2/media/upload/{$mediaId}/finalize");
                    }

                    if ($finalizeResp->successful()) {
                        $processingInfo = $finalizeResp->json('data.processing_info') ?? $finalizeResp->json('processing_info') ?? [];
                        $processingState = $processingInfo['state'] ?? null;
                        $retries = 0;

                        while (in_array($processingState, ['pending', 'in_progress'], true) && $retries < 20) {
                            $waitSecs = min(5, max(2, (int) ($processingInfo['check_after_secs'] ?? 3)));
                            sleep($waitSecs);

                            $statusResp = $this->httpClient($token, 15)
                                ->get('https://api.x.com/2/media/upload', [
                                    'command' => 'STATUS',
                                    'media_id' => $mediaId,
                                ]);

                            $processingInfo = $statusResp->json('data.processing_info') ?? $statusResp->json('processing_info') ?? [];
                            $processingState = $processingInfo['state'] ?? 'succeeded';

                            if ($processingState === 'failed') {
                                $errMsg = $processingInfo['error']['message'] ?? 'Video formatı X tarafından işlenemedi.';
                                throw new \RuntimeException("X Video İşleme Hatası: {$errMsg}");
                            }
                            $retries++;
                        }

                        return $mediaId;
                    }
                }
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable) {
            // Fallback to v1.1 below
        }

        // 2. Fallback: X API v1.1 Chunked Media Upload
        try {
            $initResponse = $this->httpClient($token, 15)
                ->asForm()
                ->post('https://upload.twitter.com/1.1/media/upload.json', [
                    'command' => 'INIT',
                    'total_bytes' => $fileSize,
                    'media_type' => $mimeType,
                    'media_category' => 'tweet_video',
                ]);

            if (! $initResponse->successful() || ! $initResponse->json('media_id_string')) {
                if ($v2AuthError !== null) {
                    throw new \RuntimeException("X (Twitter) Video Yükleme İzni (media.write) Eksik: Lütfen Sosyal Medya Kanalları -> X (Twitter) sayfasından 'İzinleri Yenile' butonuna tıklayıp açılan X ekranında 'Uygulamaya İzin Ver' diyerek video iznini onaylayın.");
                }
                return null;
            }

            $mediaId = (string) $initResponse->json('media_id_string');

            $fileHandle = fopen($videoFilePath, 'rb');
            $segmentIndex = 0;
            while (! feof($fileHandle)) {
                $chunk = fread($fileHandle, 4 * 1024 * 1024);
                $appendResponse = $this->httpClient($token, 60)
                    ->attach('media', $chunk, 'video.mp4')
                    ->post('https://upload.twitter.com/1.1/media/upload.json', [
                        'command' => 'APPEND',
                        'media_id' => $mediaId,
                        'segment_index' => $segmentIndex,
                    ]);

                if (! $appendResponse->successful()) {
                    fclose($fileHandle);
                    return null;
                }
                $segmentIndex++;
            }
            fclose($fileHandle);

            $finalizeResponse = $this->httpClient($token, 25)
                ->asForm()
                ->post('https://upload.twitter.com/1.1/media/upload.json', [
                    'command' => 'FINALIZE',
                    'media_id' => $mediaId,
                ]);

            if (! $finalizeResponse->successful()) {
                return null;
            }

            $processingState = $finalizeResponse->json('processing_info.state');
            $retries = 0;
            while (in_array($processingState, ['pending', 'in_progress'], true) && $retries < 12) {
                $waitSecs = min(5, max(2, (int) $finalizeResponse->json('processing_info.check_after_secs', 3)));
                sleep($waitSecs);
                $statusResp = $this->httpClient($token, 15)
                    ->get('https://upload.twitter.com/1.1/media/upload.json', [
                        'command' => 'STATUS',
                        'media_id' => $mediaId,
                    ]);
                $processingState = $statusResp->json('processing_info.state');
                if ($processingState === 'failed') {
                    return null;
                }
                $retries++;
            }

            return $mediaId;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable) {
            if ($v2AuthError !== null) {
                throw new \RuntimeException("X (Twitter) Video Yükleme İzni (media.write) Eksik: Lütfen Sosyal Medya Kanalları -> X (Twitter) sayfasından 'İzinleri Yenile' butonuna tıklayıp açılan X ekranında 'Uygulamaya İzin Ver' diyerek video iznini onaylayın.");
            }
            return null;
        }
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

    protected function buildFriendlyXError(int $status, mixed $rawError): string
    {
        $detail = is_array($rawError)
            ? ($rawError['detail'] ?? $rawError['title'] ?? ($rawError['errors'][0]['message'] ?? json_encode($rawError)))
            : (string) $rawError;

        if ($status === 401) {
            return 'X (Twitter) Erişim Anahtarı (Access Token) süresi dolmuş veya geçersiz. X OAuth 2.0 anahtarları 2 saatte bir yenilenir; lütfen Sosyal Medya Kanalları > X (Twitter) sayfasından hesabınızı yeniden yetkilendirin.';
        }

        if ($status === 402 || str_contains(strtolower($detail), 'credits depleted')) {
            return 'X (Twitter) API Kredisi Tükendi (402 Credits Depleted): X Developer Portal (developer.x.com) hesabınızdaki API kullanım kredisi/kotası tükenmiş görünüyor.';
        }

        if ($status === 403) {
            return "X (Twitter) Erişim/Yetki Hatası (403): X Developer Portal'da uygulamanızın 'User authentication settings' kısmında izinlerin 'Read and write' olduğundan emin olun. Detay: {$detail}";
        }

        return "X Tweet Paylaşım Hatası ({$status}): {$detail}";
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
        if (! $clientId || ! $credential->refresh_token || str_starts_with($credential->refresh_token, 'partner_rt_')) {
            return false;
        }

        try {
            $request = Http::asForm()
                ->timeout(15)
                ->withOptions(['force_ip_resolve' => 'v4']);

            if ($clientSecret) {
                $request->withBasicAuth($clientId, $clientSecret);
            }

            $response = $request->post('https://api.x.com/2/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $credential->refresh_token,
                'client_id' => $clientId,
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                return false;
            }

            $credential->update([
                'access_token' => $response->json('access_token'),
                'refresh_token' => $response->json('refresh_token', $credential->refresh_token),
                'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 7200)),
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
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

        try {
            $response = $this->httpClient($token, 15)
                ->get('https://api.x.com/2/users/me');

            if ($response->status() === 401 && $this->refreshToken($account)) {
                $account->load('credential');
                $response = $this->httpClient($account->credential->access_token, 15)
                    ->get('https://api.x.com/2/users/me');
            }

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        if ($this->isSandboxToken($token)) {
            return ['views' => 760, 'likes' => 84, 'comments' => 12, 'shares' => 19];
        }

        $response = $this->httpClient($token, 15)
            ->get("https://api.x.com/2/tweets/{$externalPostId}", [
                'tweet.fields' => 'public_metrics',
            ]);

        $metrics = $response->json('data.public_metrics', []);

        return [
            'views' => (int) ($metrics['impression_count'] ?? 0),
            'likes' => (int) ($metrics['like_count'] ?? 0),
            'comments' => (int) ($metrics['reply_count'] ?? 0),
            'shares' => (int) ($metrics['retweet_count'] ?? 0),
        ];
    }
}
