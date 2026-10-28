<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LiveStreamResource\Pages;
use App\Models\LiveStream;
use App\Models\Playlist;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\LiveStream\LiveStreamService;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class LiveStreamResource extends Resource
{
    protected static ?string $model = LiveStream::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationGroup = 'Yayın ve Otomasyon';
    protected static ?string $navigationLabel = '7/24 Canlı Yayın (Band)';
    protected static ?string $modelLabel = 'Canlı Yayın (Band)';
    protected static ?string $pluralModelLabel = '7/24 Canlı Yayın Stüdyosu (Band Kaydı)';
    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        try {
            $runningCount = LiveStream::where('status', 'running')->count();
            return $runningCount > 0 ? "🔴 {$runningCount} CANLI" : '7/24';
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        try {
            return LiveStream::where('status', 'running')->exists() ? 'danger' : 'info';
        } catch (\Throwable) {
            return 'info';
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('🎬 Band Kaydı & Video Havuzu Ayarları')
                    ->description('Video havuzundaki videoları bir TV kanalı gibi arka arkaya birleştirip 7/24 kesintisiz canlı yayına dönüştürür.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Canlı Yayın Başlığı')
                            ->default('7/24 Kesintisiz Band Yayını (YouTube & Instagram)')
                            ->placeholder('Örn: 7/24 Patenli Ayakkabılar Canlı Yayını')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Grid::make(3)
                            ->schema([
                                Select::make('source_type')
                                    ->label('Video Kaynağı')
                                    ->options([
                                        'pool' => '🎬 Tüm Video Havuzu (Otomatik)',
                                        'playlist' => '📋 Oynatma Listesi (Playlist)',
                                        'custom' => '🎯 Seçili Özel Videolar',
                                    ])
                                    ->default('pool')
                                    ->live()
                                    ->required(),

                                Select::make('play_order')
                                    ->label('Band Oynatma Sırası')
                                    ->options([
                                        'sequential' => '🔢 Sıralı (1. Video, 2. Video...)',
                                        'reverse' => '🆕 En Yeni Videodan Başla',
                                        'shuffle' => '🔀 Karışık (Rastgele Döngü)',
                                    ])
                                    ->default('sequential')
                                    ->required(),

                                Select::make('aspect_ratio_filter')
                                    ->label('Video Format Filtresi')
                                    ->options([
                                        'all' => '🌟 Tüm Videolar (Otomatik Ölçekle)',
                                        '9:16' => '📱 Sadece 9:16 Dikey Videolar',
                                        '16:9' => '🖥️ Sadece 16:9 Yatay Videolar',
                                    ])
                                    ->default('all')
                                    ->required(),
                            ]),

                        Select::make('playlist_id')
                            ->label('Oynatma Listesi Seçin')
                            ->options(fn () => Playlist::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('source_type') === 'playlist')
                            ->required(fn (Get $get): bool => $get('source_type') === 'playlist')
                            ->columnSpanFull(),

                        Select::make('custom_video_ids')
                            ->label('Döngüye Girecek Videoları Seçin')
                            ->multiple()
                            ->options(fn () => Video::orderBy('id', 'asc')->get()->mapWithKeys(fn (Video $v) => [
                                $v->id => "{$v->title} ({$v->aspect_ratio} • {$v->formatted_duration})",
                            ])->toArray())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('source_type') === 'custom')
                            ->required(fn (Get $get): bool => $get('source_type') === 'custom')
                            ->columnSpanFull(),

                        Grid::make(3)
                            ->schema([
                                Select::make('resolution_mode')
                                    ->label('Yayın Çıkış Çözünürlüğü & Yönü')
                                    ->options([
                                        'vertical_1080p' => '📱 9:16 Dikey 1080p (IG Live & YT Shorts Canlı)',
                                        'vertical_720p' => '📱 9:16 Dikey 720p (Düşük CPU / Akıcı)',
                                        'horizontal_1080p' => '🖥️ 16:9 Yatay 1080p (YouTube TV Yayını)',
                                        'horizontal_720p' => '🖥️ 16:9 Yatay 720p (Standart HD)',
                                        'copy' => '⚡ Doğrudan Aktarım (Encode Yok / Ultra Düşük CPU)',
                                    ])
                                    ->default('vertical_1080p')
                                    ->required(),

                                Select::make('bitrate_kbps')
                                    ->label('Video Bitrate (Kalite / Hız)')
                                    ->options([
                                        1500 => '1500 kbps (Düşük Bant Genişliği)',
                                        2500 => '2500 kbps (Önerilen — Stabil 7/24)',
                                        3500 => '3500 kbps (Yüksek Kalite HD)',
                                        4500 => '4500 kbps (Ultra Kalite Full HD)',
                                    ])
                                    ->default(2500)
                                    ->required(),

                                Select::make('fps')
                                    ->label('Kare Hızı (FPS)')
                                    ->options([
                                        24 => '24 FPS (Sinematik)',
                                        30 => '30 FPS (Standart — Önerilen)',
                                        60 => '60 FPS (Akıcı)',
                                    ])
                                    ->default(30)
                                    ->required(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_loop')
                                    ->label('♾️ 7/24 Sonsuz Band Döngüsü')
                                    ->helperText('Havuzdaki son video bittiğinde yayın kesilmeden 1. videodan devam eder.')
                                    ->default(true),

                                Toggle::make('auto_restart')
                                    ->label('🛡️ Otomatik Kurtarma (Watchdog)')
                                    ->helperText('İnternet veya RTMP kopmasında yayını otomatik yeniden başlatır.')
                                    ->default(true),

                                Toggle::make('mute_original_audio')
                                    ->label('🔇 Videoların Sesini Kapat')
                                    ->helperText('Telif veya gürültü önlemek için sessiz AAC akışı gönderir.')
                                    ->default(false),
                            ]),
                    ]),

                Section::make('🔴 YouTube Canlı Yayın Hedefi (RTMP / OAuth)')
                    ->description('YouTube Studio -> Canlı Yayın ekranındaki Yayın Anahtarını (Stream Key) girebilir veya bağlı YouTube hesabınızdan otomatik oluşturabilirsiniz.')
                    ->schema([
                        Toggle::make('youtube_enabled')
                            ->label('YouTube Canlı Yayınını Etkinleştir')
                            ->default(true)
                            ->live(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('youtube_rtmp_url')
                                    ->label('YouTube RTMP Sunucu Adresi (Stream URL)')
                                    ->default('rtmp://a.rtmp.youtube.com/live2')
                                    ->placeholder('rtmp://a.rtmp.youtube.com/live2')
                                    ->required(fn (Get $get): bool => (bool) $get('youtube_enabled')),

                                TextInput::make('youtube_stream_key')
                                    ->label('YouTube Yayın Anahtarı (Stream Key)')
                                    ->password()
                                    ->revealable()
                                    ->placeholder('Örn: xxxx-xxxx-xxxx-xxxx-xxxx')
                                    ->helperText('YouTube Studio > Canlı Yayın > Yayın Anahtarı alanından kopyalayın veya tablodaki "Otomatik Anahtar Al" butonunu kullanın.'),
                            ])
                            ->visible(fn (Get $get): bool => (bool) $get('youtube_enabled')),

                        Select::make('youtube_account_id')
                            ->label('Bağlı YouTube Kanalı (API ile Otomatik Yayın Oluşturmak İçin - İsteğe Bağlı)')
                            ->options(fn () => SocialAccount::with('platform')
                                ->whereHas('platform', fn ($q) => $q->where('code', 'youtube'))
                                ->get()
                                ->mapWithKeys(fn ($a) => [$a->id => "▶️ {$a->name} (@{$a->username})"])
                                ->toArray())
                            ->placeholder('Manuel RTMP Anahtarı kullanıyorsanız boş bırakabilirsiniz')
                            ->visible(fn (Get $get): bool => (bool) $get('youtube_enabled'))
                            ->columnSpanFull(),
                    ]),

                Section::make('🟣 Instagram Canlı Yayın Hedefi (Instagram Live Producer RTMPS)')
                    ->description('Bilgisayardan / Sunucudan Instagram Canlı Yayını yapmak için Instagram Live Producer RTMPS bilgilerini girin.')
                    ->schema([
                        Toggle::make('instagram_enabled')
                            ->label('Instagram Canlı Yayınını Etkinleştir (Eşzamanlı veya Tekil)')
                            ->default(false)
                            ->live(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('instagram_rtmp_url')
                                    ->label('Instagram RTMPS Sunucu URL')
                                    ->default('rtmps://edgetee-upload-iad.xx.fbcdn.net:443/rtmp/')
                                    ->placeholder('rtmps://edgetee-upload-...facebook.com:443/rtmp/')
                                    ->required(fn (Get $get): bool => (bool) $get('instagram_enabled')),

                                TextInput::make('instagram_stream_key')
                                    ->label('Instagram Yayın Anahtarı (Stream Key)')
                                    ->password()
                                    ->revealable()
                                    ->placeholder('Örn: 17954123456789012?s_bl=1&s_psm=1...')
                                    ->required(fn (Get $get): bool => (bool) $get('instagram_enabled')),
                            ])
                            ->visible(fn (Get $get): bool => (bool) $get('instagram_enabled')),

                        Placeholder::make('ig_live_guide')
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => (bool) $get('instagram_enabled'))
                            ->content(new HtmlString('
                                <div class="p-3 rounded-lg border border-pink-500/30 bg-pink-950/20 text-xs text-pink-200 leading-relaxed">
                                    <strong class="block text-pink-400 mb-1">💡 Instagram Yayın Anahtarı (Stream Key) Nasıl Alınır?</strong>
                                    1. Tarayıcıdan <strong>instagram.com</strong> adresine girin ve sol menüden <strong>+ Oluştur (Create) → Canlı Video (Live video)</strong> seçeneğine tıklayın.<br>
                                    2. Yayın başlığını yazıp hedef kitleyi <strong>Herkese Açık (Public)</strong> seçerek ilerleyin.<br>
                                    3. Karşınıza çıkan <strong>Yayın URL\'si (Stream URL)</strong> ve <strong>Yayın Anahtarı (Stream Key)</strong> bilgilerini yukarıdaki kutulara yapıştırıp <strong>Yayını Başlat</strong> butonuna basın.<br>
                                    4. Görüntü Instagram ekranına düştüğünde Instagram\'daki <strong>"Canlı Yayına Geç (Go Live)"</strong> butonuna tıklayın!
                                </div>
                            '))
                            ->columnSpanFull(),
                    ]),

                Section::make('🌐 Ek Özel RTMP Hedefi (TikTok Live / Facebook Live / Restream)')
                    ->description('Aynı anda 3. bir platforma (TikTok Live Studio, Facebook Live, Kick, Twitch vb.) yayın göndermek isterseniz açabilirsiniz.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('custom_rtmp_enabled')
                            ->label('Ek Özel RTMP Hedefini Etkinleştir')
                            ->default(false)
                            ->live(),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('custom_rtmp_name')
                                    ->label('Platform Adı')
                                    ->placeholder('Örn: TikTok Live / Facebook'),

                                TextInput::make('custom_rtmp_url')
                                    ->label('RTMP Sunucu URL')
                                    ->placeholder('rtmp://...'),

                                TextInput::make('custom_rtmp_key')
                                    ->label('Yayın Anahtarı (Stream Key)')
                                    ->password()
                                    ->revealable(),
                            ])
                            ->visible(fn (Get $get): bool => (bool) $get('custom_rtmp_enabled')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (LiveStream $record): string => match ($record->status) {
                        'running' => 'success',
                        'starting', 'restarting' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (LiveStream $record): string => match ($record->status) {
                        'running' => '🟢 CANLI YAYINDA',
                        'starting' => '⏳ BAŞLATILIYOR',
                        'restarting' => '🔄 YENİDEN BAŞLIYOR',
                        'error' => '⚠️ HATA',
                        default => '⏹️ DURDURULDU',
                    })
                    ->description(fn (LiveStream $record): ?string => $record->status === 'running' && $record->process_pid
                        ? "PID: {$record->process_pid} • Süre: {$record->uptime_formatted}"
                        : ($record->last_error ? mb_substr($record->last_error, 0, 55) . '...' : null)
                    ),

                TextColumn::make('title')
                    ->label('Yayın Başlığı & Band Kaynağı')
                    ->weight('bold')
                    ->searchable()
                    ->description(function (LiveStream $record): string {
                        $sourceLabel = match ($record->source_type) {
                            'playlist' => '📋 Oynatma Listesi',
                            'custom' => '🎯 Seçili Videolar',
                            default => '🎬 Tüm Video Havuzu',
                        };
                        $orderLabel = match ($record->play_order) {
                            'shuffle' => 'Karışık',
                            'reverse' => 'En Yeniden',
                            default => 'Sıralı (1, 2, 3...)',
                        };
                        $videosCount = $record->getEligibleVideos()->count();

                        return "{$sourceLabel} • {$videosCount} Video ({$record->formatted_loop_duration}) • {$orderLabel}";
                    }),

                TextColumn::make('targets')
                    ->label('Hedef Platformlar')
                    ->getStateUsing(function (LiveStream $record): HtmlString {
                        $badges = [];
                        if ($record->youtube_enabled) {
                            $hasKey = ! empty(trim((string) $record->youtube_stream_key));
                            $badges[] = '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:rgba(239,68,68,0.18);color:#fca5a5;border:1px solid rgba(239,68,68,0.35);">▶️ YouTube Live ' . ($hasKey ? '✓' : '⚠️') . '</span>';
                        }
                        if ($record->instagram_enabled) {
                            $hasKey = ! empty(trim((string) $record->instagram_stream_key));
                            $badges[] = '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:rgba(236,72,153,0.18);color:#f9a8d4;border:1px solid rgba(236,72,153,0.35);">📸 Instagram Live ' . ($hasKey ? '✓' : '⚠️') . '</span>';
                        }
                        if ($record->custom_rtmp_enabled) {
                            $name = e($record->custom_rtmp_name ?: 'Özel RTMP');
                            $badges[] = '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:rgba(99,102,241,0.18);color:#c7d2fe;border:1px solid rgba(99,102,241,0.35);">🌐 ' . $name . '</span>';
                        }

                        if (empty($badges)) {
                            return new HtmlString('<span class="text-xs text-gray-500">Hedef seçilmedi</span>');
                        }

                        return new HtmlString('<div class="flex flex-wrap gap-1.5">' . implode('', $badges) . '</div>');
                    }),

                TextColumn::make('resolution_mode')
                    ->label('Format & Kalite')
                    ->badge()
                    ->size(TextColumnSize::ExtraSmall)
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'vertical_1080p' => '📱 9:16 Dikey (1080p)',
                        'vertical_720p' => '📱 9:16 Dikey (720p)',
                        'horizontal_1080p' => '🖥️ 16:9 Yatay (1080p)',
                        'horizontal_720p' => '🖥️ 16:9 Yatay (720p)',
                        'copy' => '⚡ Doğrudan (Copy)',
                        default => (string) $state,
                    })
                    ->description(fn (LiveStream $record): string => "{$record->bitrate_kbps} kbps • {$record->fps} FPS"),
            ])
            ->actions([
                Action::make('start_stream')
                    ->label('Başlat')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->visible(fn (LiveStream $record): bool => $record->status !== 'running')
                    ->requiresConfirmation()
                    ->modalHeading(fn (LiveStream $record): string => "🔴 Canlı Yayını Başlat: {$record->title}")
                    ->modalDescription('Video havuzundaki videolar birleştirilerek seçili platformlara (YouTube / Instagram) 7/24 band kaydı olarak canlı yayınlanmaya başlayacaktır.')
                    ->modalSubmitActionLabel('Canlı Yayını Şimdi Başlat')
                    ->action(function (LiveStream $record) {
                        try {
                            app(LiveStreamService::class)->startStream($record);
                            Notification::make()
                                ->title('🔴 7/24 Canlı Yayın Başlatıldı!')
                                ->body("'{$record->title}' yayını arka planda (PID: {$record->fresh()->process_pid}) kesintisiz döngüde çalışıyor.")
                                ->success()
                                ->duration(8000)
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Canlı Yayın Başlatılamadı')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Action::make('stop_stream')
                    ->label('Durdur')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->button()
                    ->size('sm')
                    ->visible(fn (LiveStream $record): bool => $record->status === 'running')
                    ->requiresConfirmation()
                    ->modalHeading('⏹️ Canlı Yayını Durdur')
                    ->modalDescription('Aktif canlı yayın akışı sonlandırılacaktır. İstediğiniz zaman tekrar başlatabilirsiniz.')
                    ->action(function (LiveStream $record) {
                        app(LiveStreamService::class)->stopStream($record);
                        Notification::make()
                            ->title('Canlı Yayın Durduruldu')
                            ->body("'{$record->title}' yayını güvenli şekilde sonlandırıldı.")
                            ->warning()
                            ->send();
                    }),

                Action::make('restart_stream')
                    ->label('Yeniden Başlat')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Video Listesini Güncelle ve Yayını Yeniden Başlat')
                    ->visible(fn (LiveStream $record): bool => $record->status === 'running')
                    ->action(function (LiveStream $record) {
                        try {
                            $service = app(LiveStreamService::class);
                            $service->stopStream($record);
                            usleep(300000);
                            $service->startStream($record);
                            Notification::make()
                                ->title('Yayın ve Video Havuzu Listesi Yenilendi')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Yeniden Başlatma Hatası')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('provision_youtube')
                    ->label('YouTube Otomatik Anahtar Al')
                    ->icon('heroicon-o-key')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Bağlı YouTube Kanalından Otomatik Canlı Yayın & Anahtar Oluştur')
                    ->visible(fn (LiveStream $record): bool => (bool) $record->youtube_enabled)
                    ->action(function (LiveStream $record) {
                        try {
                            $res = app(LiveStreamService::class)->provisionYouTubeLiveStream($record);
                            Notification::make()
                                ->title('YouTube Canlı Yayın Anahtarı Hazır! 🔑')
                                ->body('Yayın Anahtarı (Stream Key) otomatik olarak tanımlandı. Artık "Başlat" butonuna basabilirsiniz.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('YouTube API Bilgilendirmesi')
                                ->body($e->getMessage())
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    }),

                Action::make('monitor')
                    ->label('Canlı Monitör')
                    ->icon('heroicon-o-computer-desktop')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Canlı Yayın Konsolu, Döngü Sırası ve Durum Monitörü')
                    ->modalWidth(MaxWidth::FourExtraLarge)
                    ->modalHeading(fn (LiveStream $record): string => "📡 Canlı Yayın Monitörü — {$record->title}")
                    ->modalContent(fn (LiveStream $record) => view('filament.modals.live-stream-monitor', ['stream' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat'),

                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Yayın Ayarlarını & Anahtarları Düzenle'),

                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->before(function (LiveStream $record) {
                        if ($record->status === 'running') {
                            app(LiveStreamService::class)->stopStream($record);
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLiveStreams::route('/'),
        ];
    }
}
