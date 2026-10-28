<?php

namespace App\Filament\Resources\LiveStreamResource\Pages;

use App\Filament\Resources\LiveStreamResource;
use App\Models\LiveStream;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\LiveStream\LiveStreamService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;

class ManageLiveStreams extends ManageRecords
{
    protected static string $resource = LiveStreamResource::class;

    public function mount(): void
    {
        parent::mount();

        try {
            $this->ensureThreePlatformChannels();
            app(LiveStreamService::class)->checkHealthAndWatchdog();
        } catch (\Throwable) {
            // Tablo veya veritabanı hazır değilse sessizce geç
        }
    }

    /**
     * 3 temel platform (YouTube, Instagram, TikTok) için bağımsız yayın kanallarının hazır olmasını sağlar.
     */
    public function ensureThreePlatformChannels(): void
    {
        $ytAccount = SocialAccount::whereHas('platform', fn ($q) => $q->where('code', 'youtube'))->first();
        $igAccount = SocialAccount::whereHas('platform', fn ($q) => $q->where('code', 'instagram'))->first();
        $ttAccount = SocialAccount::whereHas('platform', fn ($q) => $q->where('code', 'tiktok'))->first();
        $videosCount = Video::count();

        // 1. YouTube Kanalı
        if (! LiveStream::where('target_platform', 'youtube')->exists()) {
            LiveStream::create([
                'title' => '🔴 YouTube 7/24 Kesintisiz Band Yayını',
                'description' => 'Video havuzundaki videoları YouTube üzerinde dikey Shorts veya standart canlı yayın akışında kesintisiz yayınlar.',
                'target_platform' => 'youtube',
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
                'instagram_enabled' => false,
                'tiktok_enabled' => false,
                'status' => 'stopped',
                'total_videos_count' => $videosCount,
            ]);
        }

        // 2. Instagram Kanalı
        if (! LiveStream::where('target_platform', 'instagram')->exists()) {
            LiveStream::create([
                'title' => '📸 Instagram 7/24 Dikey Band Yayını',
                'description' => 'Instagram Live Producer üzerinden 9:16 dikey formatta 7/24 kesintisiz video havuzu canlı yayını.',
                'target_platform' => 'instagram',
                'source_type' => 'pool',
                'play_order' => 'sequential',
                'aspect_ratio_filter' => 'all',
                'resolution_mode' => 'vertical_1080p',
                'bitrate_kbps' => 2500,
                'fps' => 30,
                'is_loop' => true,
                'auto_restart' => true,
                'youtube_enabled' => false,
                'instagram_enabled' => true,
                'instagram_account_id' => $igAccount?->id,
                'instagram_rtmp_url' => 'rtmps://edgetee-upload-iad.xx.fbcdn.net:443/rtmp/',
                'tiktok_enabled' => false,
                'status' => 'stopped',
                'total_videos_count' => $videosCount,
            ]);
        }

        // 3. TikTok Kanalı
        if (! LiveStream::where('target_platform', 'tiktok')->exists()) {
            LiveStream::create([
                'title' => '🎵 TikTok 7/24 Dikey Canlı Yayın Akışı',
                'description' => 'TikTok Live Studio / RTMP üzerinden video havuzundaki içerikleri kesintisiz dikey canlı yayın olarak basar.',
                'target_platform' => 'tiktok',
                'source_type' => 'pool',
                'play_order' => 'sequential',
                'aspect_ratio_filter' => 'all',
                'resolution_mode' => 'vertical_1080p',
                'bitrate_kbps' => 2500,
                'fps' => 30,
                'is_loop' => true,
                'auto_restart' => true,
                'youtube_enabled' => false,
                'instagram_enabled' => false,
                'tiktok_enabled' => true,
                'tiktok_account_id' => $ttAccount?->id,
                'tiktok_rtmp_url' => 'rtmp://live-push.tiktok.com/live/',
                'status' => 'stopped',
                'total_videos_count' => $videosCount,
            ]);
        }

        // Eski tekil çoklu platform kaydını 'multi' olarak etiketle
        LiveStream::whereNull('target_platform')
            ->orWhere('target_platform', '')
            ->update(['target_platform' => 'multi', 'tiktok_enabled' => true]);
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tüm Yayınlar')
                ->badge(fn () => LiveStream::count()),

            'youtube' => Tab::make('🔴 YouTube')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('target_platform', 'youtube')->orWhere('youtube_enabled', true))
                ->badge(fn () => LiveStream::where('target_platform', 'youtube')->orWhere('youtube_enabled', true)->count()),

            'instagram' => Tab::make('📸 Instagram')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('target_platform', 'instagram')->orWhere('instagram_enabled', true))
                ->badge(fn () => LiveStream::where('target_platform', 'instagram')->orWhere('instagram_enabled', true)->count()),

            'tiktok' => Tab::make('🎵 TikTok')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('target_platform', 'tiktok')->orWhere('tiktok_enabled', true))
                ->badge(fn () => LiveStream::where('target_platform', 'tiktok')->orWhere('tiktok_enabled', true)->count()),

            'multi' => Tab::make('🌐 Çoklu Platform')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('target_platform', 'multi'))
                ->badge(fn () => LiveStream::where('target_platform', 'multi')->count()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('setup_channels')
                ->label('⚡ 3 Platformu Otomatik Yapılandır')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->tooltip('YouTube, Instagram ve TikTok için ayrı bağımsız yayın kanallarını hazırla')
                ->action(function () {
                    $this->ensureThreePlatformChannels();
                    Notification::make()
                        ->title('3 Platform İçin Ayrı Yayın Kanalları Hazırlandı')
                        ->body('🔴 YouTube, 📸 Instagram ve 🎵 TikTok için bağımsız yayın ayarları oluşturuldu.')
                        ->success()
                        ->send();
                }),

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
