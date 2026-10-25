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

        // Daily limit check
        $todayPostsCount = ScheduledPost::where('schedule_id', $schedule->id)
            ->whereDate('scheduled_at', Carbon::today())
            ->count();

        if ($todayPostsCount >= $schedule->max_posts_per_day) {
            Log::info("Schedule #{$schedule->id} reached daily limit of {$schedule->max_posts_per_day} posts.");
            return null;
        }

        $targetAccountIds = $schedule->target_social_account_ids ?: [];
        if (empty($targetAccountIds)) {
            Log::warning("Schedule #{$schedule->id} has no target social accounts selected.");
            return null;
        }

        // 1. Pick the next eligible video: from playlist if available, otherwise directly from video pool in sequence
        $playlist = $schedule->playlist;
        if ($playlist) {
            $video = $this->playlistEngine->getNextVideo($playlist);
        } else {
            // Pick next eligible video from the global pool sequentially (never used first by ID order, then oldest used)
            $video = Video::where('status', 'ready')
                ->where('is_reusable', true)
                ->orderByRaw('last_used_at IS NULL DESC, last_used_at ASC, id ASC')
                ->first();
        }

        if (! $video) {
            Log::info("Schedule #{$schedule->id} has no eligible video right now (pool empty or cooldown active).");
            return null;
        }

        // Mark last_used_at immediately so sequential runs advance to the next video
        $video->update(['last_used_at' => now()]);

        // 2. Create the scheduled post and dispatch targets
        $scheduledPost = $this->schedulingService->createScheduledPost(
            video: $video,
            scheduledAt: now(),
            socialAccountIds: $targetAccountIds,
            playlistId: $playlist?->id,
            scheduleId: $schedule->id,
            campaignId: $schedule->campaign_id
        );

        $schedule->update([
            'last_run_at' => now(),
        ]);

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

            $query = match ($orderMode) {
                'newest_first' => $query->orderBy('id', 'desc'),
                'least_used' => $query->orderByRaw('usage_count ASC, last_used_at IS NULL DESC, last_used_at ASC, id ASC'),
                'shuffle' => $query->inRandomOrder(),
                default => $query->orderBy('id', 'asc'), // 'sequential' (1 -> 2 -> 3)
            };

            if ($limit && $limit > 0) {
                $query->limit($limit);
            }

            $videos = $query->get();
        }

        if ($videos->isEmpty()) {
            return 0;
        }

        $createdCount = 0;
        $currentTime = $startAt->copy();

        foreach ($videos as $video) {
            $this->schedulingService->createScheduledPost(
                video: $video,
                scheduledAt: $currentTime->copy(),
                socialAccountIds: $socialAccountIds,
                playlistId: $playlistId
            );

            $currentTime->addMinutes($intervalMinutes);
            $createdCount++;
        }

        return $createdCount;
    }

    /**
     * Check all active schedules whose scheduled time has arrived today (supports single, multiple times per day and intervals)
     */
    public function checkAndRunDueSchedules(): int
    {
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

            // Check daily limit
            $todayPostsCount = ScheduledPost::where('schedule_id', $schedule->id)
                ->whereDate('scheduled_at', Carbon::today())
                ->count();

            if ($todayPostsCount >= $schedule->max_posts_per_day) {
                Log::info("Schedule #{$schedule->id} reached daily limit of {$schedule->max_posts_per_day} posts.");
                continue;
            }

            $post = $this->runSchedule($schedule);
            if ($post) {
                $executed++;
            }
        }

        return $executed;
    }
}
