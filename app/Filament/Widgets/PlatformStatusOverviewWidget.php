<?php

namespace App\Filament\Widgets;

use App\Enums\SocialPlatformCode;
use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class PlatformStatusOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.platform-status-overview-widget';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $pollingInterval = '30s';

    public function getViewData(): array
    {
        $today = Carbon::today();
        $platforms = SocialPlatform::all();
        $platformStats = [];

        foreach ($platforms as $platform) {
            $accounts = SocialAccount::where('platform_id', $platform->id)->get();
            $connectedCount = $accounts->where('status', 'active')->count();

            $todayPostCount = ScheduledPostTarget::where('platform_code', $platform->code->value)
                ->whereDate('published_at', $today)
                ->where('status', 'published')
                ->count();

            $hasError = $accounts->whereIn('status', ['expired', 'revoked', 'rate_limited'])->isNotEmpty();

            $statusLabel = 'Bağlantı Yok';
            $statusColor = 'gray';

            if ($connectedCount > 0) {
                if ($hasError) {
                    $statusLabel = 'Yenileme Gerekli';
                    $statusColor = 'warning';
                } else {
                    $statusLabel = "{$connectedCount} Hesap Bağlı";
                    $statusColor = 'success';
                }
            }

            $platformStats[] = [
                'code' => $platform->code->value,
                'name' => $platform->name,
                'icon' => $platform->icon,
                'status_label' => $statusLabel,
                'status_color' => $statusColor,
                'connected_count' => $connectedCount,
                'today_posts' => $todayPostCount,
                'daily_limit' => $platform->daily_post_limit,
                'is_active' => $platform->is_active,
            ];
        }

        return [
            'platforms' => $platformStats,
        ];
    }
}
