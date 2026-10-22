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

        // 1. Pick the next eligible video: from playlist if available, otherwise directly from video pool
        $playlist = $schedule->playlist;
        if ($playlist) {
            $video = $this->playlistEngine->getNextVideo($playlist);
        } else {
            // Pick next eligible video from the global pool (ordered by least recently used)
            $video = Video::where('status', 'ready')
                ->where('is_reusable', true)
                ->orderByRaw('last_used_at IS NULL DESC, last_used_at ASC')
                ->first();
        }

        if (! $video) {
            Log::info("Schedule #{$schedule->id} has no eligible video right now (pool empty or cooldown active).");
            return null;
        }

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
     * Check all active schedules whose scheduled time has arrived today
     */
    public function checkAndRunDueSchedules(): int
    {
        $nowTime = now()->format('H:i');
        $currentDayOfWeek = now()->dayOfWeekIso; // 1 = Monday, 7 = Sunday

        $schedules = Schedule::where('is_active', true)
            ->whereNotNull('playlist_id')
            ->get();

        $executed = 0;

        foreach ($schedules as $schedule) {
            $scheduleTime = substr($schedule->time_of_day, 0, 5); // 'HH:mm'
            $days = $schedule->days_of_week ?: [1, 2, 3, 4, 5, 6, 7];

            if (! in_array($currentDayOfWeek, $days)) {
                continue;
            }

            // If time matches or if it hasn't run today yet and time has passed
            $hasRunToday = $schedule->last_run_at && $schedule->last_run_at->isToday();

            if (! $hasRunToday && $nowTime >= $scheduleTime) {
                $post = $this->runSchedule($schedule);
                if ($post) {
                    $executed++;
                }
            }
        }

        return $executed;
    }
}
