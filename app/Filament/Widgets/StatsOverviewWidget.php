<?php

namespace App\Filament\Widgets;

use App\Enums\PublishStatus;
use App\Enums\VideoStatus;
use App\Models\ApiUsageLog;
use App\Models\Schedule;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $today = Carbon::today();

        $totalVideos = Video::count();
        $readyVideos = Video::where('status', VideoStatus::READY)->count();

        $todayScheduled = ScheduledPost::whereDate('scheduled_at', $today)->count();
        $todayPublished = ScheduledPostTarget::whereDate('published_at', $today)
            ->where('status', PublishStatus::PUBLISHED)
            ->count();

        $failedPublications = ScheduledPostTarget::where('status', PublishStatus::FAILED)->count();

        $connectedAccounts = SocialAccount::where('status', 'active')->count();
        $activeAutomations = Schedule::where('is_active', true)->count();

        $nextPost = ScheduledPost::where('status', PublishStatus::SCHEDULED)
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at', 'asc')
            ->first();

        $nextPostTime = $nextPost 
            ? $nextPost->scheduled_at->diffForHumans() 
            : 'Planlanan yok';

        $totalAiCost = ApiUsageLog::where('provider', 'openai')->sum('estimated_cost_usd');

        return [
            Stat::make('Video Havuzu', "{$readyVideos} / {$totalVideos}")
                ->description("{$readyVideos} yayına hazır video")
                ->descriptionIcon('heroicon-m-film')
                ->color('primary'),

            Stat::make('Bugünkü Yayınlar', "{$todayPublished} / {$todayScheduled}")
                ->description('Başarıyla gönderilen / Planlanan')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('success'),

            Stat::make('Başarısız Gönderiler', $failedPublications)
                ->description($failedPublications > 0 ? 'İnceleme gerekiyor' : 'Tüm yayınlar sorunsuz')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($failedPublications > 0 ? 'danger' : 'gray'),

            Stat::make('Sosyal Hesaplar & Otomasyon', "{$connectedAccounts} Hesap / {$activeAutomations} Aktif")
                ->description('Otomasyon motoru hazır')
                ->descriptionIcon('heroicon-m-share')
                ->color('info'),

            Stat::make('Bir Sonraki Paylaşım', $nextPostTime)
                ->description($nextPost ? $nextPost->scheduled_at->format('d.m.Y H:i') : 'Bekleyen görev yok')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('AI İçerik Maliyeti', '$' . number_format($totalAiCost, 4))
                ->description('Toplam OpenAI GPT kullanımı')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('secondary'),
        ];
    }
}
