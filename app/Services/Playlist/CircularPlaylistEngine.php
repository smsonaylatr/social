<?php

namespace App\Services\Playlist;

use App\Enums\PlaylistLoopMode;
use App\Enums\PublishStatus;
use App\Enums\VideoStatus;
use App\Models\Playlist;
use App\Models\PlaylistCursor;
use App\Models\PlaylistItem;
use App\Models\ScheduledPostTarget;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CircularPlaylistEngine
{
    /**
     * Pick the next eligible video from playlist, respecting loop mode,
     * cursor position, cooldown period, and platform duplicate protection policies.
     */
    public function getNextVideo(Playlist $playlist, ?string $targetPlatform = null): ?Video
    {
        $cursor = $playlist->cursor()->firstOrCreate([
            'playlist_id' => $playlist->id,
        ], [
            'current_index' => 0,
            'cycle_count' => 0,
        ]);

        $items = $this->getOrderedItems($playlist);

        if ($items->isEmpty()) {
            $playlist->update(['status' => 'waiting_content']);
            return null;
        }

        $totalItems = $items->count();
        $startIndex = $cursor->current_index % $totalItems;

        // Try to find the next valid video starting from current_index
        $candidate = null;
        $chosenIndex = null;

        for ($i = 0; $i < $totalItems; $i++) {
            $checkIndex = ($startIndex + $i) % $totalItems;
            $item = $items->get($checkIndex);

            if (! $item || ! $item->is_active || ! $item->video) {
                continue;
            }

            $video = $item->video;

            if ($video->status !== VideoStatus::READY) {
                continue;
            }

            // Check eligibility (cooldown, platform repeat restrictions)
            if ($this->isVideoEligible($video, $playlist, $targetPlatform)) {
                $candidate = $video;
                $chosenIndex = $checkIndex;
                break;
            }
        }

        // If no candidate found in current pass, evaluate cycle loop
        if (! $candidate) {
            if ($playlist->is_circular && ! $playlist->pause_when_exhausted) {
                // Check if any reusable video is past its cooldown
                for ($checkIndex = 0; $checkIndex < $totalItems; $checkIndex++) {
                    $item = $items->get($checkIndex);
                    if ($item && $item->is_active && $item->video && $item->video->is_reusable) {
                        if ($this->isVideoEligible($item->video, $playlist, $targetPlatform, true)) {
                            $candidate = $item->video;
                            $chosenIndex = $checkIndex;
                            break;
                        }
                    }
                }
            }

            if (! $candidate) {
                $playlist->update(['status' => 'waiting_content']);
                return null;
            }
        }

        // Advance cursor position
        $nextIndex = ($chosenIndex + 1) % $totalItems;
        $isCycleComplete = ($chosenIndex + 1) >= $totalItems;

        $cursor->update([
            'current_index' => $nextIndex,
            'current_video_id' => $candidate->id,
            'cycle_count' => $isCycleComplete ? $cursor->cycle_count + 1 : $cursor->cycle_count,
            'last_dispatched_at' => now(),
        ]);

        $playlist->update(['status' => 'active']);

        return $candidate;
    }

    /**
     * Check if a video is eligible to be published right now
     */
    public function isVideoEligible(Video $video, Playlist $playlist, ?string $targetPlatform = null, bool $allowRecycled = false): bool
    {
        // 1. Is video restricted on target platform?
        if ($targetPlatform && is_array($video->restricted_platforms) && in_array($targetPlatform, $video->restricted_platforms)) {
            return false;
        }

        $cooldownDays = $playlist->cooldown_days ?: 14;

        // 2. Strict Platform Duplicate Protection (e.g. YouTube & X policies)
        if ($targetPlatform) {
            $isPlatformDuplicateBlocked = $this->isDuplicateOnPlatformBlocked($video, $targetPlatform, $cooldownDays);
            if ($isPlatformDuplicateBlocked) {
                return false;
            }
        }

        // 3. If video was never used, it's immediately eligible
        if ($video->usage_count === 0 || ! $video->last_used_at) {
            return true;
        }

        // 4. Reusable check
        if (! $video->is_reusable) {
            return false;
        }

        // 5. Global Cooldown Check
        if ($video->last_used_at->diffInDays(now()) < $cooldownDays) {
            return false;
        }

        return true;
    }

    /**
     * Platform-specific duplicate policy enforcement:
     * - YouTube and X have strict duplicate content policies.
     * - Disallows republishing identical video file hash on YouTube/X if already published recently.
     */
    protected function isDuplicateOnPlatformBlocked(Video $video, string $platformCode, int $cooldownDays): bool
    {
        // YouTube: Duplicate video re-upload is heavily penalized or rejected by YouTube API
        if ($platformCode === 'youtube') {
            $hasBeenPublishedOnYouTube = ScheduledPostTarget::where('platform_code', 'youtube')
                ->where('status', PublishStatus::PUBLISHED)
                ->whereHas('scheduledPost', fn ($q) => $q->where('video_id', $video->id))
                ->exists();

            // Strict policy: Do not re-upload identical video to YouTube
            if ($hasBeenPublishedOnYouTube) {
                return true;
            }
        }

        // X (Twitter): Strict repeat threshold (minimum 30 days or never for identical media)
        if ($platformCode === 'x') {
            $recentXPost = ScheduledPostTarget::where('platform_code', 'x')
                ->where('status', PublishStatus::PUBLISHED)
                ->whereHas('scheduledPost', fn ($q) => $q->where('video_id', $video->id))
                ->where('published_at', '>=', now()->subDays(max(30, $cooldownDays)))
                ->exists();

            if ($recentXPost) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get ordered playlist items according to playlist loop mode
     */
    protected function getOrderedItems(Playlist $playlist): Collection
    {
        $query = $playlist->items()
            ->with('video')
            ->where('is_active', true);

        return match ($playlist->loop_mode) {
            PlaylistLoopMode::SEQUENTIAL => $query->orderBy('sort_order', 'asc')->get(),
            PlaylistLoopMode::PRIORITY => $query->orderBy('priority_score', 'desc')->orderBy('sort_order', 'asc')->get(),
            PlaylistLoopMode::SHUFFLE => $query->inRandomOrder()->get(),
        };
    }
}
