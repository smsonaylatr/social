<?php

namespace App\Console\Commands;

use App\Services\LiveStream\LiveStreamService;
use Illuminate\Console\Command;

class ManageLiveStreamsCommand extends Command
{
    protected $signature = 'socialflow:live-watchdog';

    protected $description = '7/24 çalışan canlı yayınların (YouTube & Instagram) sağlık durumunu denetler ve kopanları otomatik yeniden başlatır.';

    public function handle(LiveStreamService $service): int
    {
        $stats = $service->checkHealthAndWatchdog();

        $this->info(sprintf(
            'Canlı Yayın Watchdog: %d kontrol edildi | %d sağlıklı | %d yeniden başlatıldı | %d hatalı',
            $stats['checked'],
            $stats['healthy'],
            $stats['restarted'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
