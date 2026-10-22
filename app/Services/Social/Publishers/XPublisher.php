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
        $token = $this->getAccessToken($account);
        $caption = mb_substr($this->buildFullCaption($target), 0, 280);

        if (str_starts_with($token, 'mock_') || config('app.env') === 'testing') {
            $tweetId = '178' . rand(10000000000000, 99999999999999);
            return [
                'post_id' => $tweetId,
                'post_url' => "https://x.com/{$account->username}/status/{$tweetId}",
                'status' => 'published',
                'is_mock' => true,
            ];
        }

        $videoFilePath = Storage::disk($video->disk ?: 'public')->path($video->storage_path);
        if (! file_exists($videoFilePath)) {
            throw new \RuntimeException('X için yüklenecek video dosyası bulunamadı.');
        }

        $fileSize = filesize($videoFilePath);

        // 1. INIT chunked upload
        $initResponse = Http::withToken($token)
            ->asForm()
            ->post('https://upload.twitter.com/1.1/media/upload.json', [
                'command' => 'INIT',
                'total_bytes' => $fileSize,
                'media_type' => $video->mime_type,
                'media_category' => 'tweet_video',
            ]);

        if (! $initResponse->successful()) {
            throw new \RuntimeException('X Media INIT Hatası: ' . $initResponse->body());
        }

        $mediaId = $initResponse->json('media_id_string');

        // 2. APPEND video chunk
        $fileHandle = fopen($videoFilePath, 'rb');
        $segmentIndex = 0;
        while (! feof($fileHandle)) {
            $chunk = fread($fileHandle, 4 * 1024 * 1024); // 4MB chunks
            $appendResponse = Http::withToken($token)
                ->attach('media', $chunk, 'video.mp4')
                ->post('https://upload.twitter.com/1.1/media/upload.json', [
                    'command' => 'APPEND',
                    'media_id' => $mediaId,
                    'segment_index' => $segmentIndex,
                ]);

            if (! $appendResponse->successful()) {
                fclose($fileHandle);
                throw new \RuntimeException('X Media APPEND Hatası: ' . $appendResponse->body());
            }
            $segmentIndex++;
        }
        fclose($fileHandle);

        // 3. FINALIZE
        $finalizeResponse = Http::withToken($token)
            ->asForm()
            ->post('https://upload.twitter.com/1.1/media/upload.json', [
                'command' => 'FINALIZE',
                'media_id' => $mediaId,
            ]);

        if (! $finalizeResponse->successful()) {
            throw new \RuntimeException('X Media FINALIZE Hatası: ' . $finalizeResponse->body());
        }

        // 4. Create Tweet: POST https://api.twitter.com/2/tweets
        $tweetResponse = Http::withToken($token)
            ->post('https://api.twitter.com/2/tweets', [
                'text' => $caption,
                'media' => [
                    'media_ids' => [$mediaId],
                ],
            ]);

        if (! $tweetResponse->successful()) {
            throw new \RuntimeException('X Tweet Paylaşım Hatası: ' . $tweetResponse->body());
        }

        $tweetId = $tweetResponse->json('data.id');

        return [
            'post_id' => $tweetId,
            'post_url' => "https://x.com/{$account->username}/status/{$tweetId}",
            'status' => 'published',
        ];
    }
}
