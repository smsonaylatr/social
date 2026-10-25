<?php

namespace App\Services\Publisher;

use App\Enums\PublishStatus;
use App\Jobs\DispatchPlatformPostJob;
use App\Models\AiContentGeneration;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\AI\Contracts\AiContentProviderInterface;
use App\Services\SEO\HashtagOptimizationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SchedulingService
{
    /**
     * Create a scheduled post record with independent targets for each social account
     */
    public function createScheduledPost(
        Video $video,
        Carbon $scheduledAt,
        array $socialAccountIds,
        ?int $playlistId = null,
        ?int $scheduleId = null,
        ?int $campaignId = null
    ): ScheduledPost {
        return DB::transaction(function () use ($video, $scheduledAt, $socialAccountIds, $playlistId, $scheduleId, $campaignId) {
            $scheduledPost = ScheduledPost::create([
                'workspace_id' => $video->workspace_id,
                'brand_id' => $video->brand_id,
                'video_id' => $video->id,
                'playlist_id' => $playlistId,
                'schedule_id' => $scheduleId,
                'campaign_id' => $campaignId,
                'scheduled_at' => $scheduledAt,
                'status' => PublishStatus::SCHEDULED,
            ]);

            $accounts = SocialAccount::whereIn('id', $socialAccountIds)
                ->with('platform')
                ->get();

            $aiService = app(AiContentProviderInterface::class);
            $hashtagService = app(HashtagOptimizationService::class);

            foreach ($accounts as $account) {
                $platformCode = $account->platform->code->value;

                // Ensure AI content generation exists for this platform
                $aiContent = AiContentGeneration::where('video_id', $video->id)
                    ->where('platform_code', $platformCode)
                    ->first();

                if (! $aiContent) {
                    $generated = $aiService->generateForSinglePlatform($video, $platformCode, $video->brand);
                    $caption = $generated['caption'] ?? ($video->custom_caption ?: $video->title);
                    $title = $generated['title'] ?? $video->title;
                    $hashtags = $generated['hashtags'] ?? $hashtagService->getOptimizedHashtags($video, $platformCode);
                    $productUrl = $generated['suggested_product_url'] ?? $video->product_url;
                } else {
                    $caption = $aiContent->caption;
                    $title = $aiContent->title;
                    $hashtags = $aiContent->hashtags ?? $hashtagService->getOptimizedHashtags($video, $platformCode);
                    $productUrl = $aiContent->suggested_product_url ?? $video->product_url;
                }

                $caption = \App\Services\AI\OpenAiContentService::cleanSystemLabels($caption);
                $title = \App\Services\AI\OpenAiContentService::cleanSystemLabels($title);

                $correlationId = 'corr_' . Str::uuid()->toString();
                $idempotencyKey = hash('sha256', "post_{$scheduledPost->id}_acc_{$account->id}_ts_{$scheduledAt->timestamp}");

                ScheduledPostTarget::create([
                    'scheduled_post_id' => $scheduledPost->id,
                    'social_account_id' => $account->id,
                    'platform_code' => $platformCode,
                    'correlation_id' => $correlationId,
                    'idempotency_key' => $idempotencyKey,
                    'status' => PublishStatus::SCHEDULED,
                    'title' => $title,
                    'caption' => $caption,
                    'hashtags' => $hashtags,
                    'product_url' => $productUrl,
                    'attempt_count' => 0,
                ]);
            }

            return $scheduledPost;
        });
    }

    /**
     * Dispatch due posts using distributed atomic lock
     */
    public function dispatchDuePosts(int $batchSize = 20): int
    {
        $lockKey = 'socialflow_scheduling_lock';

        // Prevent overlapping runs
        $lock = Cache::lock($lockKey, 30);
        if (! $lock->get()) {
            return 0;
        }

        try {
            $duePosts = ScheduledPost::where('status', PublishStatus::SCHEDULED)
                ->where('scheduled_at', '<=', now())
                ->with(['targets' => fn ($q) => $q->where('status', PublishStatus::SCHEDULED)])
                ->limit($batchSize)
                ->get();

            $dispatchedCount = 0;

            foreach ($duePosts as $post) {
                $post->update(['status' => PublishStatus::QUEUED]);

                foreach ($post->targets as $target) {
                    $target->update(['status' => PublishStatus::QUEUED]);
                    DispatchPlatformPostJob::dispatch($target->id);
                    $dispatchedCount++;
                }
            }

            // Arka planda beklemesin, kuyruğu hemen işle ve sosyal medyaya yükle
            if ($dispatchedCount > 0) {
                try {
                    \Illuminate\Support\Facades\Artisan::call('queue:work', [
                        '--stop-when-empty' => true,
                        '--max-jobs' => 20,
                        '--tries' => 3,
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Immediate queue work error: " . $e->getMessage());
                }
            }

            return $dispatchedCount;
        } finally {
            $lock->release();
        }
    }
}
