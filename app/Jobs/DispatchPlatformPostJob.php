<?php

namespace App\Jobs;

use App\Enums\PublishStatus;
use App\Models\PublicationAttempt;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostTarget;
use App\Services\Social\SocialPublisherManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchPlatformPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30; // base exponential backoff seconds

    public function __construct(public int $targetId) {}

    public function handle(SocialPublisherManager $publisherManager): void
    {
        $target = ScheduledPostTarget::with([
            'scheduledPost.video',
            'socialAccount.platform',
            'socialAccount.credential',
        ])->find($this->targetId);

        if (! $target) {
            return;
        }

        // Idempotency check: Never publish twice!
        if ($target->status === PublishStatus::PUBLISHED) {
            Log::info("Idempotency guard: Target #{$target->id} ({$target->platform_code}) is already published. Skipping.");
            return;
        }

        $target->increment('attempt_count');
        $target->update(['status' => PublishStatus::UPLOADING]);

        $attemptNumber = $target->attempt_count;
        $video = $target->scheduledPost->video;
        $account = $target->socialAccount;

        try {
            $publisher = $publisherManager->getPublisher($target->platform_code);
            $result = $publisher->publish($video, $target, $account);

            // Record attempt
            PublicationAttempt::create([
                'scheduled_post_target_id' => $target->id,
                'attempt_number' => $attemptNumber,
                'status' => 'success',
                'http_status_code' => 200,
                'request_payload_summary' => "Caption: {$target->caption}, Video: {$video->original_filename}",
                'response_summary' => json_encode($result),
                'attempted_at' => now(),
            ]);

            $target->update([
                'status' => PublishStatus::PUBLISHED,
                'published_at' => now(),
                'external_post_id' => $result['post_id'] ?? null,
                'external_post_url' => $result['post_url'] ?? null,
                'error_message' => null,
            ]);

            // Update video usage metrics
            $video->increment('usage_count', 1, ['last_used_at' => now()]);
            $account->update(['last_posted_at' => now()]);

            $this->updateParentPostStatus($target->scheduledPost);

        } catch (\Throwable $e) {
            Log::error("Platform publish failed for target #{$target->id} ({$target->platform_code}): " . $e->getMessage());

            PublicationAttempt::create([
                'scheduled_post_target_id' => $target->id,
                'attempt_number' => $attemptNumber,
                'status' => 'failed',
                'http_status_code' => 500,
                'error_message' => $e->getMessage(),
                'attempted_at' => now(),
            ]);

            if ($this->attempts() < $this->tries) {
                $target->update([
                    'status' => PublishStatus::RETRYING,
                    'error_message' => $e->getMessage(),
                ]);
                // Exponential backoff with jitter (5-10s)
                $jitter = rand(5, 10);
                $this->release(($this->backoff * $this->attempts()) + $jitter);
            } else {
                $target->update([
                    'status' => PublishStatus::FAILED,
                    'error_message' => $e->getMessage(),
                ]);
                $this->updateParentPostStatus($target->scheduledPost);
            }
        }
    }

    protected function updateParentPostStatus(ScheduledPost $parentPost): void
    {
        $targets = $parentPost->targets()->get();
        $total = $targets->count();
        $published = $targets->where('status', PublishStatus::PUBLISHED)->count();
        $failed = $targets->where('status', PublishStatus::FAILED)->count();

        if ($published === $total) {
            $parentPost->update(['status' => PublishStatus::PUBLISHED]);
        } elseif ($published > 0 && ($published + $failed === $total)) {
            $parentPost->update(['status' => PublishStatus::PARTIALLY_PUBLISHED]);
        } elseif ($failed === $total) {
            $parentPost->update(['status' => PublishStatus::FAILED]);
        }
    }
}
