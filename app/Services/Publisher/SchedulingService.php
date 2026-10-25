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
        ?int $campaignId = null,
        bool $useAlgorithmicTiming = false
    ): ScheduledPost {
        return DB::transaction(function () use ($video, $scheduledAt, $socialAccountIds, $playlistId, $scheduleId, $campaignId, $useAlgorithmicTiming) {
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

            // Algoritmik Keşfet ve Anti-Spam zamanlama kontrolü
            $schedule = $scheduleId ? \App\Models\Schedule::find($scheduleId) : null;
            $isAlgo = ($schedule && $schedule->schedule_type === 'algorithmic_growth') || $useAlgorithmicTiming;

            /** @var \App\Services\Scheduling\AlgorithmicGrowthSchedulerService $algoService */
            $algoService = app(\App\Services\Scheduling\AlgorithmicGrowthSchedulerService::class);

            $staggeredTimes = [];
            if ($isAlgo || ($schedule && $schedule->anti_spam_shield)) {
                $staggeredTimes = $algoService->generateStaggeredTargetTimes(
                    accounts: $accounts,
                    baseTime: $scheduledAt,
                    enableJitter: $schedule ? $schedule->enable_smart_jitter : true,
                    enableStagger: $schedule ? $schedule->enable_staggered_dispatch : true
                );
            }

            $aiService = app(AiContentProviderInterface::class);
            $hashtagService = app(HashtagOptimizationService::class);

            foreach ($accounts as $account) {
                $platformCode = $account->platform->code->value;
                $targetScheduledAt = $staggeredTimes[$account->id] ?? $scheduledAt;

                // Ensure AI content generation exists for this platform and has non-empty caption
                $aiContent = AiContentGeneration::where('video_id', $video->id)
                    ->where('platform_code', $platformCode)
                    ->first();

                if (! $aiContent || empty(trim((string) $aiContent->caption))) {
                    $generated = $aiService->generateForSinglePlatform($video, $platformCode, $video->brand);
                    $rawCaption = ! empty(trim((string) ($generated['caption'] ?? '')))
                        ? $generated['caption']
                        : ($video->custom_caption ?: 'Patenli ayakkabı ve tekerlekli spor ayakkabı modelleri ile yürümek çok daha eğlenceli! ✨');
                    $rawTitle = ! empty(trim((string) ($generated['title'] ?? ''))) ? $generated['title'] : $video->title;
                    $productUrl = ! empty($generated['suggested_product_url'])
                        ? $generated['suggested_product_url']
                        : ($video->product_url ?: ($video->brand?->website ?? 'https://patenliayakkabilar.com'));
                } else {
                    $rawCaption = $aiContent->caption;
                    $rawTitle = $aiContent->title;
                    $productUrl = $aiContent->suggested_product_url ?: ($video->product_url ?: ($video->brand?->website ?? 'https://patenliayakkabilar.com'));
                }

                // Hashtagler her zaman doğrudan hashtag havuzundan çekilir, platform limitine göre doldurulur ve sürekli döner
                $hashtags = $hashtagService->getOptimizedHashtags($video, $platformCode);

                $caption = \App\Services\AI\OpenAiContentService::cleanSystemLabels($rawCaption);
                $title = \App\Services\AI\OpenAiContentService::cleanSystemLabels($rawTitle);

                // Kesin güvenlik: Başlık veya Açıklama ASLA boş gönderilemez!
                if (empty(trim((string) $caption))) {
                    $caption = 'Patenli ayakkabı ve tekerlekli spor ayakkabı modelleri ile yürümek çok daha eğlenceli! ✨';
                }

                // SEO Anahtar Kelime Hafızasından beslenmeyi garanti altına al
                $seoKeywords = $hashtagService->getRelevantKeywords($video, $platformCode, limit: 4);
                $hasSeoKeyword = false;
                foreach ($seoKeywords as $kw) {
                    if (mb_stripos($caption, $kw) !== false) {
                        $hasSeoKeyword = true;
                        break;
                    }
                }
                if (! $hasSeoKeyword && ! empty($seoKeywords)) {
                    $kw1 = mb_convert_case($seoKeywords[0], MB_CASE_TITLE, 'UTF-8');
                    $kw2 = ! empty($seoKeywords[1]) ? mb_convert_case($seoKeywords[1], MB_CASE_TITLE, 'UTF-8') : 'Tekerlekli Ayakkabı';
                    if (str_contains($caption, "\n\n✨")) {
                        $caption = preg_replace('/(\n\n✨)/u', "\n\n✨ {$kw1} & {$kw2} modelleri\n✨", $caption, 1);
                    } else {
                        $caption .= "\n\n✨ {$kw1} & {$kw2} modelleri ile eğlence dolu adımlar!";
                    }
                }

                $correlationId = 'corr_' . Str::uuid()->toString();
                $idempotencyKey = hash('sha256', "post_{$scheduledPost->id}_acc_{$account->id}_ts_{$scheduledAt->timestamp}");

                ScheduledPostTarget::create([
                    'scheduled_post_id' => $scheduledPost->id,
                    'social_account_id' => $account->id,
                    'platform_code' => $platformCode,
                    'scheduled_at' => $targetScheduledAt,
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
            $now = now();

            // Zamanı gelmiş tüm platform hedeflerini tespit et (kademeli ve rastgele saatleri destekler)
            $dueTargets = ScheduledPostTarget::where('status', PublishStatus::SCHEDULED)
                ->where(function ($q) use ($now) {
                    $q->where(function ($sub) use ($now) {
                        $sub->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '<=', $now);
                    })->orWhere(function ($sub) use ($now) {
                        $sub->whereNull('scheduled_at')
                            ->whereHas('scheduledPost', function ($postQ) use ($now) {
                                $postQ->where('scheduled_at', '<=', $now);
                            });
                    });
                })
                ->whereHas('scheduledPost', function ($q) {
                    $q->whereIn('status', [PublishStatus::SCHEDULED, PublishStatus::QUEUED, PublishStatus::PROCESSING]);
                })
                ->with('scheduledPost')
                ->limit($batchSize)
                ->get();

            $dispatchedCount = 0;

            foreach ($dueTargets as $target) {
                $target->update(['status' => PublishStatus::QUEUED]);

                if ($target->scheduledPost && $target->scheduledPost->status === PublishStatus::SCHEDULED) {
                    $target->scheduledPost->update(['status' => PublishStatus::QUEUED]);
                }

                DispatchPlatformPostJob::dispatch($target->id);
                $dispatchedCount++;
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

    /**
     * Kısmen başarılı veya başarısız olan gönderilerdeki başarısız platform hedeflerini
     * (örn: YouTube uploadLimitExceeded) yeniden kuyruğa alır ve ana gönderi statüsünü günceller.
     */
    public function requeueFailedTargets(?int $scheduledPostId = null, ?string $platformCode = null): int
    {
        $query = ScheduledPostTarget::where('status', PublishStatus::FAILED);

        if ($scheduledPostId) {
            $query->where('scheduled_post_id', $scheduledPostId);
        }

        if ($platformCode) {
            $query->where('platform_code', $platformCode);
        }

        $failedTargets = $query->with('scheduledPost')->get();
        if ($failedTargets->isEmpty()) {
            return 0;
        }

        $requeuedCount = 0;
        $affectedPosts = collect();

        foreach ($failedTargets as $target) {
            $target->update([
                'status' => PublishStatus::QUEUED,
                'attempt_count' => 0,
                'error_message' => $target->error_message ? "Yeniden kuyruğa alındı (Önceki durum: {$target->error_message})" : null,
            ]);

            $requeuedCount++;

            if ($target->scheduledPost) {
                $affectedPosts->push($target->scheduledPost);
            }
        }

        // Ana gönderilerin statülerini güncelle (Örn: 'Kısmen Başarılı' -> 'Kuyrukta')
        foreach ($affectedPosts->unique('id') as $parentPost) {
            $parentPost->updateStatusBasedOnTargets();
        }

        return $requeuedCount;
    }
}
