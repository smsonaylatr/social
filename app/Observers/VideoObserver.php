<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Video;

class VideoObserver
{
    public function created(Video $video): void
    {
        AuditLog::record(
            'video_uploaded',
            $video,
            [
                'title' => $video->title,
                'original_filename' => $video->original_filename,
                'duration_seconds' => $video->duration_seconds,
                'file_size' => $video->file_size,
                'aspect_ratio' => $video->aspect_ratio,
            ]
        );
    }

    public function deleted(Video $video): void
    {
        AuditLog::record(
            'video_deleted',
            $video,
            null,
            [
                'title' => $video->title,
                'original_filename' => $video->original_filename,
            ]
        );
    }
}
