<?php

namespace App\Filament\Resources\LiveStreamResource\Pages;

use App\Filament\Resources\LiveStreamResource;
use App\Models\LiveStream;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\LiveStream\LiveStreamService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\MaxWidth;

class ManageLiveStreams extends ManageRecords
{
    protected static string $resource = LiveStreamResource::class;

    public function mount(): void
    {
        parent::mount();

        try {
            // Eğer henüz hiç canlı yayın kaydı yoksa varsayılan YouTube & Instagram 7/24 Band Yayınını hazır oluştur
            if (LiveStream::count() === 0) {
                $ytAccount = SocialAccount::whereHas('platform', fn ($q) => $q->where('code', 'youtube'))->first();
                $igAccount = SocialAccount::whereHas('platform', fn ($q) => $q->where('code', 'instagram'))->first();

                LiveStream::create([
                    'title' => '7/24 Video Havuzu Band Yayını (YouTube & Instagram)',
                    'description' => 'Video havuzundaki tüm videoları kesintisiz döngü halinde canlı yayınlar.',
                    'source_type' => 'pool',
                    'play_order' => 'sequential',
                    'aspect_ratio_filter' => 'all',
                    'resolution_mode' => 'vertical_1080p',
                    'bitrate_kbps' => 2500,
                    'fps' => 30,
                    'is_loop' => true,
                    'auto_restart' => true,
                    'youtube_enabled' => true,
                    'youtube_account_id' => $ytAccount?->id,
                    'youtube_rtmp_url' => 'rtmp://a.rtmp.youtube.com/live2',
                    'instagram_enabled' => true,
                    'instagram_account_id' => $igAccount?->id,
                    'instagram_rtmp_url' => 'rtmps://edgetee-upload-iad.xx.fbcdn.net:443/rtmp/',
                    'status' => 'stopped',
                    'total_videos_count' => Video::count(),
                ]);
            }

            app(LiveStreamService::class)->checkHealthAndWatchdog();
        } catch (\Throwable) {
            // Tablo henüz migrate edilmemişse sessizce geç
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_pool')
                ->label('Havuz Listesini Yenile')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $service = app(LiveStreamService::class);
                    $stats = $service->checkHealthAndWatchdog();
                    $refreshed = $service->refreshActiveConcatFiles();

                    Notification::make()
                        ->title('Video Havuzu ve Yayın Durumu Güncellendi')
                        ->body("Aktif yayın listeleri yenilendi ({$refreshed} aktif yayın, {$stats['healthy']} sağlıklı süreç).")
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('Yeni Canlı Yayın Ekle')
                ->icon('heroicon-o-plus-circle')
                ->modalWidth(MaxWidth::FourExtraLarge),
        ];
    }
}
