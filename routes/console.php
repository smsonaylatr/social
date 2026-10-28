<?php

use App\Services\Automation\AutomationOrchestratorService;
use App\Services\Publisher\SchedulingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('socialflow:check-schedules', function (AutomationOrchestratorService $orchestrator) {
    $this->info('Kontrol ediliyor: Zamanı gelen otomasyon kuralları...');
    $count = $orchestrator->checkAndRunDueSchedules();
    $this->info("✓ {$count} adet otomasyon kuralı çalıştırıldı.");
})->purpose('Zamanı gelen sosyal medya otomasyonlarını çalıştırır');

Artisan::command('socialflow:process-due-posts', function (SchedulingService $schedulingService) {
    $this->info('Kontrol ediliyor: Bekleyen planlı gönderiler...');
    $dispatched = $schedulingService->dispatchDuePosts();
    $this->info("✓ {$dispatched} adet platform hedefi kuyruğa alındı.");
})->purpose('Zamanı gelen planlanmış gönderileri kuyruğa aktarır');

// Laravel Scheduler Registration (Cron)
Schedule::command('socialflow:check-schedules')->everyMinute()->withoutOverlapping();
Schedule::command('socialflow:process-due-posts')->everyMinute()->withoutOverlapping();
Schedule::command('socialflow:live-watchdog')->everyMinute()->withoutOverlapping();
