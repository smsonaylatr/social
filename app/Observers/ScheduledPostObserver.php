<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\ScheduledPost;

class ScheduledPostObserver
{
    public function created(ScheduledPost $post): void
    {
        AuditLog::record(
            'post_scheduled',
            $post,
            [
                'video_id' => $post->video_id,
                'scheduled_at' => (string) $post->scheduled_at,
                'status' => $post->status?->value ?? (string) $post->status,
            ]
        );
    }

    public function updated(ScheduledPost $post): void
    {
        if ($post->isDirty('status')) {
            $newStatus = $post->status?->value ?? (string) $post->status;
            $oldStatus = $post->getOriginal('status');
            $oldStatusVal = is_object($oldStatus) && property_exists($oldStatus, 'value') ? $oldStatus->value : (string) $oldStatus;

            if ($newStatus === 'published') {
                AuditLog::record(
                    'post_published',
                    $post,
                    ['status' => $newStatus],
                    ['status' => $oldStatusVal]
                );
            } elseif ($newStatus === 'failed') {
                AuditLog::record(
                    'post_failed',
                    $post,
                    ['status' => $newStatus, 'notes' => $post->notes],
                    ['status' => $oldStatusVal]
                );
            }
        }
    }
}
