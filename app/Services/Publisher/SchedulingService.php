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
        bool $useAlgorithmicTiming = false,
        ?string $postType = null
    ): ScheduledPost {
        return DB::transaction(function () use ($video, $scheduledAt, $socialAccountIds, $playlistId, $scheduleId, $campaignId, $useAlgorithmicTiming, $postType) {
            $schedule = $scheduleId ? \App\Models\Schedule::find($scheduleId) : null;
            $effectivePostType = $postType ?: ($schedule?->post_type ?? 'video');

            $scheduledPost = ScheduledPost::create([
                'workspace_id' => $video->workspace_id,
                'brand_id' => $video->brand_id,
                'video_id' => $video->id,
                'post_type' => $effectivePostType,
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

                $isTextMode = ($effectivePostType === 'text');

                // Hashtagler: Metin gönderilerinde Google SEO zenginliği için geniş havuzdan (15 adet) beslen
                $hashtagLimit = $isTextMode ? 15 : null;
                $hashtags = $hashtagService->getOptimizedHashtags($video, $platformCode, $hashtagLimit);

                $caption = \App\Services\AI\OpenAiContentService::cleanSystemLabels($rawCaption);
                $title = \App\Services\AI\OpenAiContentService::cleanSystemLabels($rawTitle);

                // Metin postlarında emoji kesinlikle kullanılmaz!
                if ($isTextMode) {
                    $caption = \App\Services\AI\OpenAiContentService::stripEmojis($caption);
                    $title = \App\Services\AI\OpenAiContentService::stripEmojis($title);
                }

                // Kesin güvenlik: Başlık veya Açıklama ASLA boş gönderilemez!
                if (empty(trim((string) $caption))) {
                    $caption = $isTextMode
                        ? 'Patenli ayakkabı ve tekerlekli spor ayakkabı modelleri ile yürümek çok daha eğlenceli!'
                        : 'Patenli ayakkabı ve tekerlekli spor ayakkabı modelleri ile yürümek çok daha eğlenceli! ✨';
                }

                // Platforma özel web sitesi linki kuralı:
                // Facebook ve X -> https://patenliayakkabilar.com (Tam HTTPS)
                // LinkedIn -> patenliayakkabilar.com (Yalın alan adı)
                $targetProductUrl = match ($platformCode) {
                    'linkedin' => 'patenliayakkabilar.com',
                    'facebook', 'x' => 'https://patenliayakkabilar.com',
                    default => 'https://patenliayakkabilar.com',
                };

                // SEO Anahtar Kelime Hafızasından beslenmeyi garanti altına al (Google sıralaması için)
                $seoKeywords = $hashtagService->getRelevantKeywords($video, $platformCode, limit: 6);
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
                    $kw3 = ! empty($seoKeywords[2]) ? mb_convert_case($seoKeywords[2], MB_CASE_TITLE, 'UTF-8') : 'Çocuk Patenli Ayakkabı';
                    if ($isTextMode) {
                        // Metin postlarında Google SEO odaklı zengin anahtar kelimeler
                        $caption .= "\n\n- {$kw1} ve {$kw2} modelleri\n- {$kw3} ile konforlu ve güvenli adımlar";
                    } else {
                        if (str_contains($caption, "\n\n✨")) {
                            $caption = preg_replace('/(\n\n✨)/u', "\n\n✨ {$kw1} & {$kw2} modelleri\n✨", $caption, 1);
                        } else {
                            $caption .= "\n\n✨ {$kw1} & {$kw2} modelleri ile eğlence dolu adımlar!";
                        }
                    }
                }

                $correlationId = 'corr_' . Str::uuid()->toString();
                $idempotencyKey = hash('sha256', "post_{$scheduledPost->id}_acc_{$account->id}_ts_{$scheduledAt->timestamp}");

                ScheduledPostTarget::create([
                    'scheduled_post_id' => $scheduledPost->id,
                    'social_account_id' => $account->id,
                    'platform_code' => $platformCode,
                    'post_type' => $effectivePostType,
                    'scheduled_at' => $targetScheduledAt,
                    'correlation_id' => $correlationId,
                    'idempotency_key' => $idempotencyKey,
                    'status' => PublishStatus::SCHEDULED,
                    'title' => $title,
                    'caption' => $caption,
                    'hashtags' => $hashtags,
                    'product_url' => $targetProductUrl,
                    'attempt_count' => 0,
                ]);
            }

            return $scheduledPost;
        }, 5);
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

    /**
     * Kanal bağlantısı kesilip yeniden bağlandığında (ON DELETE CASCADE nedeniyle)
     * hedefi (ScheduledPostTarget) silinmiş gönderileri ve eski hesap ID'si kalmış kuralları otomatik onarır.
     */
    public function repairOrphanedPostsAndSchedules(?int $specificPostId = null): int
    {
        $activeAccounts = SocialAccount::where('status', 'active')
            ->with('platform')
            ->orderByDesc('id')
            ->get();

        if ($activeAccounts->isEmpty()) {
            return 0;
        }

        $activeIds = $activeAccounts->pluck('id')->all();

        // 1. Otomatik Yayın Kurallarında (Schedule) silinmiş hesap ID'lerini aktif hesaplarla eşleştir
        $schedules = \App\Models\Schedule::all();
        foreach ($schedules as $schedule) {
            $targetIds = is_array($schedule->target_social_account_ids) ? $schedule->target_social_account_ids : [];
            $validIds = array_values(array_intersect($targetIds, $activeIds));

            if (count($validIds) !== count($targetIds) || empty($validIds)) {
                $nameLower = mb_strtolower((string) $schedule->name, 'UTF-8');
                $matchedPlatformAccount = null;

                foreach ($activeAccounts as $acc) {
                    $code = $acc->platform?->code instanceof \BackedEnum ? $acc->platform->code->value : (string) $acc->platform?->code;
                    $pName = mb_strtolower((string) ($acc->platform?->name ?? $code), 'UTF-8');
                    if (
                        ($code !== '' && str_contains($nameLower, $code))
                        || ($pName !== '' && str_contains($nameLower, $pName))
                        || ($code === 'x' && str_contains($nameLower, 'twitter'))
                    ) {
                        $matchedPlatformAccount = $acc;
                        break;
                    }
                }

                if ($matchedPlatformAccount && ! in_array($matchedPlatformAccount->id, $validIds, true)) {
                    $validIds[] = $matchedPlatformAccount->id;
                }

                // Eğer hala boşsa, en son yeniden bağlanan aktif hesabı (örn. Pinterest) ata
                if (empty($validIds)) {
                    $pinterestAcc = $activeAccounts->first(function ($acc) {
                        $code = $acc->platform?->code instanceof \BackedEnum ? $acc->platform->code->value : (string) $acc->platform?->code;
                        return $code === 'pinterest';
                    });
                    $fallbackAcc = $pinterestAcc ?: $activeAccounts->first();
                    if ($fallbackAcc) {
                        $validIds[] = $fallbackAcc->id;
                    }
                }

                if (! empty($validIds)) {
                    $schedule->update(['target_social_account_ids' => array_values(array_unique($validIds))]);
                }
            }
        }

        // 2. Hedefi (ScheduledPostTarget) silinmiş/boş kalmış ScheduledPost kayıtlarını onar
        $orphanQuery = ScheduledPost::doesntHave('targets')->with(['video.brand', 'schedule']);
        if ($specificPostId) {
            $orphanQuery->where('id', $specificPostId);
        }

        $orphanPosts = $orphanQuery->get();
        if ($orphanPosts->isEmpty()) {
            return 0;
        }

        $aiService = app(AiContentProviderInterface::class);
        $hashtagService = app(HashtagOptimizationService::class);
        $repairedCount = 0;

        foreach ($orphanPosts as $post) {
            $video = $post->video;
            if (! $video) {
                continue;
            }

            $targetAccountIds = [];
            if ($post->schedule && ! empty($post->schedule->target_social_account_ids)) {
                $targetAccountIds = array_values(array_intersect((array) $post->schedule->target_social_account_ids, $activeIds));
            }

            if (empty($targetAccountIds)) {
                $pinterestAcc = $activeAccounts->first(function ($acc) {
                    $code = $acc->platform?->code instanceof \BackedEnum ? $acc->platform->code->value : (string) $acc->platform?->code;
                    return $code === 'pinterest';
                });
                $fallbackAcc = $pinterestAcc ?: $activeAccounts->first();
                if ($fallbackAcc) {
                    $targetAccountIds = [$fallbackAcc->id];
                }
            }

            $accountsForPost = $activeAccounts->whereIn('id', $targetAccountIds);
            $effectivePostType = $post->post_type ?: ($post->schedule?->post_type ?? 'video');
            $isTextMode = ($effectivePostType === 'text');

            foreach ($accountsForPost as $account) {
                $platformCode = $account->platform?->code instanceof \BackedEnum
                    ? $account->platform->code->value
                    : (string) $account->platform?->code;

                $aiContent = AiContentGeneration::where('video_id', $video->id)
                    ->where('platform_code', $platformCode)
                    ->first();

                if (! $aiContent || empty(trim((string) $aiContent->caption))) {
                    $generated = $aiService->generateForSinglePlatform($video, $platformCode, $video->brand);
                    $rawCaption = ! empty(trim((string) ($generated['caption'] ?? '')))
                        ? $generated['caption']
                        : ($video->custom_caption ?: 'Patenli ayakkabı ve tekerlekli spor ayakkabı modelleri ile yürümek çok daha eğlenceli! ✨');
                    $rawTitle = ! empty(trim((string) ($generated['title'] ?? ''))) ? $generated['title'] : $video->title;
                } else {
                    $rawCaption = $aiContent->caption;
                    $rawTitle = $aiContent->title;
                }

                $hashtags = $hashtagService->getOptimizedHashtags($video, $platformCode, $isTextMode ? 15 : null);
                $caption = \App\Services\AI\OpenAiContentService::cleanSystemLabels($rawCaption);
                $title = \App\Services\AI\OpenAiContentService::cleanSystemLabels($rawTitle);

                if ($isTextMode) {
                    $caption = \App\Services\AI\OpenAiContentService::stripEmojis($caption);
                    $title = \App\Services\AI\OpenAiContentService::stripEmojis($title);
                }

                if (empty(trim((string) $caption))) {
                    $caption = $isTextMode
                        ? 'Patenli ayakkabı ve tekerlekli spor ayakkabı modelleri ile yürümek çok daha eğlenceli!'
                        : 'Patenli ayakkabı ve tekerlekli spor ayakkabı modelleri ile yürümek çok daha eğlenceli! ✨';
                }

                $targetProductUrl = match ($platformCode) {
                    'linkedin' => 'patenliayakkabilar.com',
                    default => 'https://patenliayakkabilar.com',
                };

                ScheduledPostTarget::create([
                    'scheduled_post_id' => $post->id,
                    'social_account_id' => $account->id,
                    'platform_code' => $platformCode,
                    'post_type' => $effectivePostType,
                    'scheduled_at' => $post->scheduled_at ?? now(),
                    'correlation_id' => 'corr_' . Str::uuid()->toString(),
                    'idempotency_key' => hash('sha256', "post_{$post->id}_acc_{$account->id}_repair_" . uniqid()),
                    'status' => PublishStatus::QUEUED,
                    'title' => $title,
                    'caption' => $caption,
                    'hashtags' => $hashtags,
                    'product_url' => $targetProductUrl,
                    'attempt_count' => 0,
                ]);
            }

            $post->update(['status' => PublishStatus::QUEUED]);
            $repairedCount++;
        }

        return $repairedCount;
    }
}
