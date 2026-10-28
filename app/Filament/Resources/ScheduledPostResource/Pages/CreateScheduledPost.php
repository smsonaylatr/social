<?php

namespace App\Filament\Resources\ScheduledPostResource\Pages;

use App\Filament\Resources\ScheduledPostResource;
use App\Models\Video;
use App\Services\Publisher\SchedulingService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateScheduledPost extends CreateRecord
{
    protected static string $resource = ScheduledPostResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $video = Video::findOrFail($data['video_id']);
        $scheduledAt = Carbon::parse($data['scheduled_at']);
        $socialAccountIds = $data['social_account_ids'] ?? [];

        $service = app(SchedulingService::class);

        return $service->createScheduledPost(
            video: $video,
            scheduledAt: $scheduledAt,
            socialAccountIds: $socialAccountIds,
            postType: $data['post_type'] ?? 'video',
        );
    }
}
