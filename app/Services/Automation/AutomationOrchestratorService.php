<?php

namespace App\Services\Automation;

use App\Enums\PublishStatus;
use App\Models\Brand;
use App\Models\Schedule;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostTarget;
use App\Models\Video;
use App\Services\Playlist\CircularPlaylistEngine;
use App\Services\Publisher\SchedulingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutomationOrchestratorService
{
    public function __construct(
        protected CircularPlaylistEngine $playlistEngine,
        protected SchedulingService $schedulingService
    ) {}

    /**
     * Run an active schedule rule, select next circular video, and queue publication targets
     */
    public function runSchedule(Schedule $schedule): ?ScheduledPost
    {
        if (! $schedule->is_active) {
            Log::info("Schedule #{$schedule->id} ({$schedule->name}) is inactive, skipping.");
            return null;
        }

        // Daily limit check (only if max_posts_per_day is set and not algorithmic_growth mode)
        if ($schedule->schedule_type !== 'algorithmic_growth' && $schedule->max_posts_per_day && $schedule->max_posts_per_day > 0) {
            $todayPostsCount = ScheduledPost::where('schedule_id', $schedule->id)
                ->whereDate('scheduled_at', Carbon::today())
                ->count();

            if ($todayPostsCount >= $schedule->max_posts_per_day) {
                Log::info("Schedule #{$schedule->id} reached daily limit of {$schedule->max_posts_per_day} posts.");
                return null;
            }
        }

        $targetAccountIds = $schedule->target_social_account_ids ?: [];
        if (empty($targetAccountIds)) {
            Log::warning("Schedule #{$schedule->id} has no target social accounts selected.");
            return null;
        }

        // 1. Pick the next eligible video: Her platform/kural kendi bağımsız sıralamasını takip eder!
        $video = $schedule->getNextVideo();

        if (! $video) {
            Log::info("Schedule #{$schedule->id} ({$schedule->name}) has no eligible video right now (pool empty).");
            return null;
        }

        // Bu platformun imlecini (cursor) ve çalışma zamanını güncelle
        $video->update(['last_used_at' => now()]);
        $schedule->update([
            'last_video_id' => $video->id,
            'last_run_at' => now(),
        ]);

        // 2. Create the scheduled post and dispatch targets
        $scheduledPost = $this->schedulingService->createScheduledPost(
            video: $video,
            scheduledAt: now(),
            socialAccountIds: $targetAccountIds,
            playlistId: null,
            scheduleId: $schedule->id,
            campaignId: $schedule->campaign_id
        );

        // 3. Immediately dispatch targets into the queue
        $this->schedulingService->dispatchDuePosts();

        Log::info("Schedule #{$schedule->id} successfully executed for video #{$video->id} ('{$video->title}').");

        return $scheduledPost;
    }

    /**
     * Build a sequential schedule queue in ScheduledPost table for all ready videos
     */
    public function buildSequentialQueue(
        array $socialAccountIds,
        Carbon $startAt,
        int $intervalMinutes = 360,
        string $orderMode = 'sequential',
        ?int $playlistId = null,
        ?int $limit = null
    ): int {
        if (empty($socialAccountIds)) {
            return 0;
        }

        if ($playlistId) {
            $playlist = \App\Models\Playlist::with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order', 'asc')->with('video')])->find($playlistId);
            $videos = $playlist
                ? $playlist->items->pluck('video')->filter(fn ($v) => $v && $v->status->value === 'ready')->values()
                : collect();
        } else {
            $query = Video::where('status', 'ready');

            if ($orderMode === 'sequential') {
                $lastScheduledVideoId = ScheduledPostTarget::join('scheduled_posts', 'scheduled_post_targets.scheduled_post_id', '=', 'scheduled_posts.id')
                    ->whereIn('scheduled_post_targets.social_account_id', $socialAccountIds)
                    ->whereNotNull('scheduled_posts.video_id')
                    ->orderBy('scheduled_post_targets.id', 'desc')
                    ->value('scheduled_posts.video_id') ?? 0;
                $query->orderByRaw('id > ? DESC, id ASC', [$lastScheduledVideoId]);
            } elseif ($orderMode === 'id_asc') {
                $query->orderBy('id', 'asc');
            } else {
                $query = match ($orderMode) {
                    'newest_first' => $query->orderBy('id', 'desc'),
                    'least_used' => $query->orderByRaw('usage_count ASC, last_used_at IS NULL DESC, last_used_at ASC, id ASC'),
                    'shuffle' => $query->inRandomOrder(),
                    default => $query->orderBy('id', 'asc'),
                };
            }

            if ($limit && $limit > 0) {
                $query->limit($limit);
            }

            $videos = $query->get();
        }

        if ($videos->isEmpty()) {
            return 0;
        }

        $createdCount = 0;

        // Eğer intervalMinutes 0 ise, günde 5 algoritmik keşfet dalgasına otomatik yerleştir
        $isAlgorithmic = ($intervalMinutes === 0);
        $algoService = app(\App\Services\Scheduling\AlgorithmicGrowthSchedulerService::class);
        $waveSlots = $isAlgorithmic ? $algoService->generateUpcomingWaveSlots($startAt, $videos->count(), 'max_capacity') : [];

        $currentTime = $startAt->copy();

        foreach ($videos as $index => $video) {
            $postTime = $isAlgorithmic ? ($waveSlots[$index] ?? $currentTime->copy()) : $currentTime->copy();

            $video->update([
                'last_used_at' => $postTime,
                'usage_count' => \Illuminate\Support\Facades\DB::raw('usage_count + 1'),
            ]);

            $this->schedulingService->createScheduledPost(
                video: $video,
                scheduledAt: $postTime,
                socialAccountIds: $socialAccountIds,
                playlistId: $playlistId,
                useAlgorithmicTiming: $isAlgorithmic
            );

            if (! $isAlgorithmic) {
                $currentTime->addMinutes($intervalMinutes);
            }
            $createdCount++;
        }

        return $createdCount;
    }

    /**
     * Henüz yayınlanmamış tüm planlı gönderileri (scheduled / queued)
     * günde 5 dalga halindeki algoritmik keşfet saatlerine göre yeniden takvimler.
     * Eksik kalan platform hedeflerini de (örn: LinkedIn) otomatik tamamlar.
     */
    public function reschedulePendingPostsToAlgorithmicWaves(string $strategy = 'max_capacity'): int
    {
        $pendingPosts = ScheduledPost::whereIn('status', [
            PublishStatus::SCHEDULED,
            PublishStatus::QUEUED
        ])
        ->orderBy('scheduled_at', 'asc')
        ->orderBy('id', 'asc')
        ->get();

        if ($pendingPosts->isEmpty()) {
            return 0;
        }

        /** @var \App\Services\Scheduling\AlgorithmicGrowthSchedulerService $algoService */
        $algoService = app(\App\Services\Scheduling\AlgorithmicGrowthSchedulerService::class);

        $waveSlots = $algoService->generateUpcomingWaveSlots(now(), $pendingPosts->count(), $strategy);

        $allActiveAccounts = \App\Models\SocialAccount::where('is_active', true)->with('platform')->get();
        if ($allActiveAccounts->isEmpty()) {
            $allActiveAccounts = \App\Models\SocialAccount::with('platform')->get();
        }

        $rescheduledCount = 0;

        foreach ($pendingPosts as $index => $post) {
            $newTime = $waveSlots[$index] ?? now()->addHours($index * 3);

            $post->update([
                'scheduled_at' => $newTime,
                'status' => PublishStatus::SCHEDULED,
            ]);

            // Eksik platform hedeflerini tamamla (LinkedIn vb. daha önce düşmüşse geri ekle)
            $targets = $post->targets()->with('socialAccount.platform')->get();
            $existingAccountIds = $targets->pluck('social_account_id')->toArray();
            $missingAccounts = $allActiveAccounts->reject(fn ($a) => in_array($a->id, $existingAccountIds));

            $aiService = app(\App\Services\AI\Contracts\AiContentProviderInterface::class);
            $hashtagService = app(\App\Services\SEO\HashtagOptimizationService::class);

            foreach ($missingAccounts as $missingAccount) {
                $platformCode = $missingAccount->platform?->code?->value ?? (string) $missingAccount->platform?->code;
                if (empty($platformCode)) {
                    continue;
                }

                $aiContent = \App\Models\AiContentGeneration::where('video_id', $post->video_id)
                    ->where('platform_code', $platformCode)
                    ->first();

                $rawCaption = $aiContent?->caption ?? 'Patenli ayakkabı modelleri ile keyifli adımlar! ✨';
                $rawTitle = $aiContent?->title ?? $post->video?->title ?? 'Patenli Ayakkabı';

                $hashtags = $hashtagService->getOptimizedHashtags($post->video, $platformCode);
                $caption = \App\Services\AI\OpenAiContentService::cleanSystemLabels($rawCaption);
                $title = \App\Services\AI\OpenAiContentService::cleanSystemLabels($rawTitle);

                ScheduledPostTarget::create([
                    'scheduled_post_id' => $post->id,
                    'social_account_id' => $missingAccount->id,
                    'platform_code' => $platformCode,
                    'scheduled_at' => $newTime,
                    'correlation_id' => 'corr_' . \Illuminate\Support\Str::uuid()->toString(),
                    'idempotency_key' => hash('sha256', "post_{$post->id}_acc_{$missingAccount->id}_ts_{$newTime->timestamp}"),
                    'status' => PublishStatus::SCHEDULED,
                    'title' => $title,
                    'caption' => $caption,
                    'hashtags' => $hashtags,
                    'product_url' => $aiContent?->suggested_product_url ?: 'https://patenliayakkabilar.com',
                    'attempt_count' => 0,
                ]);
            }

            // Yenilenmiş hedefleri çek ve zamanlarını kademelendir
            $freshTargets = $post->targets()->with('socialAccount.platform')->get();
            $accounts = $freshTargets->pluck('socialAccount')->filter()->values();

            $staggeredTimes = $algoService->generateStaggeredTargetTimes(
                accounts: $accounts,
                baseTime: $newTime,
                enableJitter: true,
                enableStagger: true
            );

            foreach ($freshTargets as $target) {
                $targetTime = $staggeredTimes[$target->social_account_id] ?? $newTime;
                $target->update([
                    'scheduled_at' => $targetTime,
                    'status' => PublishStatus::SCHEDULED,
                ]);
            }

            $rescheduledCount++;
        }

        return $rescheduledCount;
    }

    /**
     * Her platform için bağımsız, spam engeline takılmayan ve
     * o platformun en yüksek izlenme getiren zirve saatlerine özel kurallar oluşturur.
     */
    public function setupDefaultPlatformSpecificRules(): array
    {
        $accounts = \App\Models\SocialAccount::with('platform')->get();
        $createdRules = [];

        $platformConfigs = [
            'instagram' => [
                'name' => 'Instagram Reels',
                'times' => ['09:30', '12:45', '16:15', '19:15', '22:00'],
                'max_posts' => 5,
            ],
            'tiktok' => [
                'name' => 'TikTok',
                'times' => ['10:00', '13:00', '16:30', '19:45', '22:15'],
                'max_posts' => 5,
            ],
            'youtube' => [
                'name' => 'YouTube Shorts',
                'times' => ['09:45', '12:30', '16:00', '19:00', '21:45'],
                'max_posts' => 5,
            ],
            'facebook' => [
                'name' => 'Facebook',
                'times' => ['10:15', '13:15', '16:45', '19:30', '22:00'],
                'max_posts' => 5,
            ],
            'x' => [
                'name' => 'X (Twitter)',
                'times' => ['09:15', '12:15', '15:45', '18:45', '21:30'],
                'max_posts' => 5,
            ],
            'linkedin' => [
                'name' => 'LinkedIn',
                'times' => ['09:00', '12:30', '16:45', '19:30'],
                'max_posts' => 4,
            ],
            'pinterest' => [
                'name' => 'Pinterest',
                'times' => ['13:00', '16:30', '19:30', '22:00'],
                'max_posts' => 4,
            ],
        ];

        foreach ($platformConfigs as $platformCode => $cfg) {
            $acc = $accounts->first(function ($a) use ($platformCode) {
                $code = $a->platform?->code?->value ?? (string) $a->platform?->code ?? (string) $a->platform_id;
                return strtolower($code) === strtolower($platformCode) || (int) $a->platform_id === match ($platformCode) {
                    'facebook' => 1,
                    'instagram' => 2,
                    'youtube' => 3,
                    'x' => 4,
                    'pinterest' => 5,
                    'tiktok' => 6,
                    'linkedin' => 7,
                    default => 0,
                };
            });

            if (! $acc) {
                continue;
            }

            $acc->update(['is_active' => true]);

            $schedule = Schedule::firstOrNew([
                'name' => $cfg['name'],
            ]);

            $schedule->fill([
                'workspace_id' => 1,
                'brand_id' => 1,
                'schedule_type' => 'algorithmic_growth',
                'post_type' => 'video',
                'growth_strategy' => 'max_capacity',
                'anti_spam_shield' => true,
                'enable_smart_jitter' => true,
                'enable_staggered_dispatch' => true,
                'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
                'target_social_account_ids' => [$acc->id],
                'posting_times' => $cfg['times'],
                'time_of_day' => $cfg['times'][0] . ':00',
                'max_posts_per_day' => $cfg['max_posts'],
                'is_active' => true,
            ]);
            $schedule->save();

            $createdRules[] = $cfg['name'];
        }

        return $createdRules;
    }

    /**
     * Check all active schedules whose scheduled time has arrived today (supports single, multiple times per day and intervals)
     */
    public function checkAndRunDueSchedules(): int
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('socialflow_orchestrator_check_lock', 45);
        if (! $lock->get()) {
            return 0;
        }

        try {
            $now = now();
            $nowTime = $now->format('H:i');
            $currentDayOfWeek = $now->dayOfWeekIso; // 1 = Monday, 7 = Sunday

            $schedules = Schedule::where('is_active', true)->get();

            $executed = 0;

            foreach ($schedules as $schedule) {
                $days = $schedule->days_of_week ?: [1, 2, 3, 4, 5, 6, 7];

                if (! in_array($currentDayOfWeek, $days)) {
                    continue;
                }

                $dailyTimes = $schedule->getDailyRunTimes();
                if (empty($dailyTimes)) {
                    continue;
                }

                // Find all slot times that are <= current time
                $dueSlots = array_values(array_filter($dailyTimes, fn ($t) => $t <= $nowTime));
                if (empty($dueSlots)) {
                    // No slot time has arrived yet today
                    continue;
                }

                // The latest due time slot (e.g., '14:00' when current time is 14:05)
                $latestDueSlot = end($dueSlots);
                $dueTimestamp = Carbon::today()->setTimeFromTimeString($latestDueSlot);

                // Check if this schedule has already run on or after the latest due time slot today
                if ($schedule->last_run_at && $schedule->last_run_at->gte($dueTimestamp)) {
                    continue;
                }

                // Check daily limit (only if max_posts_per_day is set and greater than 0)
                if ($schedule->max_posts_per_day && $schedule->max_posts_per_day > 0) {
                    $todayPostsCount = ScheduledPost::where('schedule_id', $schedule->id)
                        ->whereDate('scheduled_at', Carbon::today())
                        ->count();

                    if ($todayPostsCount >= $schedule->max_posts_per_day) {
                        Log::info("Schedule #{$schedule->id} reached daily limit of {$schedule->max_posts_per_day} posts.");
                        continue;
                    }
                }

                $post = $this->runSchedule($schedule);
                if ($post) {
                    $executed++;
                }
            }

            return $executed;
        } finally {
            $lock->release();
        }
    }
}
