<?php

namespace App\Jobs;

use App\Services\Publisher\SchedulingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessScheduledPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SchedulingService $schedulingService): void
    {
        $schedulingService->dispatchDuePosts();
    }
}
