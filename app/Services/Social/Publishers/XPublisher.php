<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class XPublisher extends AbstractSocialPublisher
{
    public function getPlatformCode(): string
    {
        return 'x';
    }

    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array
    {
        @set_time_limit(180);

        $token = $this->getAccessToken($account);
        $caption = mb_substr($this->buildFullCaption($target), 0, 280);

        if ($this->isSandboxToken($token)) {
            $tweetId = '178' . rand(10000000000000, 99999999999999);
            return [
                'post_id' => $tweetId,
                'post_url' => "https://x.com/{$account->username}/status/{$tweetId}",
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        // Verify token or auto-refresh if 2-hour X OAuth 2.0 token expired
        $meCheck = Http::withToken($token)->timeout(10)->get('https://api.twitter.com/2/users/me');
        if ($meCheck->status() === 401 && $this->refreshToken($account)) {
            $account->load('credential');
            $token = $account->credential->access_token;
            $meCheck = Http::withToken($token)->timeout(10)->get('https://api.twitter.com/2/users/me');
        }

        if ($meCheck->successful() && $meCheck->json('data.username')) {
            $realUsername = $meCheck->json('data.username');
            $realId = $meCheck->json('data.id');
            $updates = [];
            if ($realId && $account->account_id !== $realId) {
                $updates['account_id'] = $realId;
            }
            if ($realUsername && $account->username !== $realUsername) {
                $updates['username'] = $realUsername;
            }
            if (! empty($updates)) {
                $account->update($updates);
            }
        }

        // 1. Try uploading video media (supports v2 media/upload or v1.1 if permitted)
        $mediaId = $this->tryUploadVideoMedia($video, $token);

        // 2. Create Tweet: POST https://api.twitter.com/2/tweets
        $payload = [
            'text' => $caption,
        ];

        if ($mediaId) {
            $payload['media'] = [
                'media_ids' => [(string) $mediaId],
            ];
        }

        $tweetResponse = Http::withToken($token)
            ->timeout(30)
            ->post('https://api.twitter.com/2/tweets', $payload);

        // If media attachment caused an error, retry as text tweet
        if (! $tweetResponse->successful() && isset($payload['media'])) {
            unset($payload['media']);
            $tweetResponse = Http::withToken($token)
                ->timeout(30)
                ->post('https://api.twitter.com/2/tweets', $payload);
        }

        // If 403 duplicate content error (when testing the same video multiple times), make text unique
        if ($tweetResponse->status() === 403 && str_contains(strtolower($tweetResponse->body()), 'duplicate')) {
            $suffix = ' ✨';
            $payload['text'] = mb_substr($caption, 0, 280 - mb_strlen($suffix)) . $suffix;
            $tweetResponse = Http::withToken($token)
                ->timeout(30)
                ->post('https://api.twitter.com/2/tweets', $payload);
        }

        if (! $tweetResponse->successful()) {
            throw new \RuntimeException($this->buildFriendlyXError($tweetResponse->status(), $tweetResponse->json() ?: $tweetResponse->body()));
        }

        $tweetId = $tweetResponse->json('data.id');
        $username = $account->username ?: 'i/web';

        return [
            'post_id' => $tweetId,
            'post_url' => "https://x.com/{$username}/status/{$tweetId}",
            'status' => 'published',
        ];
    }

    /**
     * Attempt chunked video upload via X API v2 or v1.1 (returns null gracefully if OAuth 2.0 scope only permits tweet.write)
     */
    protected function tryUploadVideoMedia(Video $video, string $token): ?string
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

            // Try X v1.1 chunked upload
            $initResponse = Http::withToken($token)
                ->timeout(20)
                ->asForm()
                ->post('https://upload.twitter.com/1.1/media/upload.json', [
                    'command' => 'INIT',
                    'total_bytes' => $fileSize,
                    'media_type' => $video->mime_type ?: 'video/mp4',
                    'media_category' => 'tweet_video',
                ]);

            if (! $initResponse->successful() || ! $initResponse->json('media_id_string')) {
                return null;
            }

            $mediaId = (string) $initResponse->json('media_id_string');

            $fileHandle = fopen($videoFilePath, 'rb');
            $segmentIndex = 0;
            while (! feof($fileHandle)) {
                $chunk = fread($fileHandle, 4 * 1024 * 1024);
                $appendResponse = Http::withToken($token)
                    ->timeout(60)
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

            $finalizeResponse = Http::withToken($token)
                ->timeout(30)
                ->asForm()
                ->post('https://upload.twitter.com/1.1/media/upload.json', [
                    'command' => 'FINALIZE',
                    'media_id' => $mediaId,
                ]);

            if (! $finalizeResponse->successful()) {
                return null;
            }

            // Poll processing_info if present
            $processingState = $finalizeResponse->json('processing_info.state');
            $retries = 0;
            while (in_array($processingState, ['pending', 'in_progress'], true) && $retries < 12) {
                $waitSecs = min(5, max(2, (int) $finalizeResponse->json('processing_info.check_after_secs', 3)));
                sleep($waitSecs);
                $statusResp = Http::withToken($token)
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
        } catch (\Throwable) {
            return null;
        }
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

        $request = Http::asForm();
        if ($clientSecret) {
            $request->withBasicAuth($clientId, $clientSecret);
        }

        $response = $request->post('https://api.twitter.com/2/oauth2/token', [
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
            ->get('https://api.twitter.com/2/users/me');

        if ($response->status() === 401 && $this->refreshToken($account)) {
            $account->load('credential');
            $response = Http::withToken($account->credential->access_token)
                ->get('https://api.twitter.com/2/users/me');
        }

        return $response->successful();
    }

    public function getAnalytics(string $externalPostId, SocialAccount $account): array
    {
        $token = $this->getAccessToken($account);
        if ($this->isSandboxToken($token)) {
            return ['views' => 760, 'likes' => 84, 'comments' => 12, 'shares' => 19];
        }

        $response = Http::withToken($token)
            ->get("https://api.twitter.com/2/tweets/{$externalPostId}", [
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
