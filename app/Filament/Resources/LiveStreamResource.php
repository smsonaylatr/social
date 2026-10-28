<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LiveStreamResource\Pages;
use App\Models\LiveStream;
use App\Models\Playlist;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\LiveStream\LiveStreamService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class LiveStreamResource extends Resource
{
    protected static ?string $model = LiveStream::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationGroup = 'Yayın ve Otomasyon';
    protected static ?string $navigationLabel = '7/24 Canlı Yayın (Band)';
    protected static ?string $modelLabel = 'Canlı Yayın Kanalı';
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
                Section::make('🎯 Canlı Yayın Platformu ve Kanal Türü')
                    ->description('Her platform için bağımsız, platforma özel optimize edilmiş 7/24 canlı yayın kanalı oluşturabilir veya çoklu yayın (Simulcast) seçebilirsiniz.')
                    ->schema([
                        Select::make('target_platform')
                            ->label('Hedef Sosyal Medya Platformu')
                            ->options([
                                'youtube' => '🔴 YouTube 7/24 Canlı Yayın (Shorts & Standart)',
                                'instagram' => '📸 Instagram 7/24 Canlı Yayın (Live Producer)',
                                'tiktok' => '🎵 TikTok 7/24 Canlı Yayın (Live Studio / RTMP)',
                                'multi' => '🌐 Çoklu Platform (Aynı Anda Eşzamanlı Simulcast)',
                            ])
                            ->default('youtube')
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (string $state, Set $set, Get $get) {
                                $currentTitle = $get('title');
                                if (empty($currentTitle) || str_contains($currentTitle, '7/24')) {
                                    $set('title', match ($state) {
                                        'youtube' => '🔴 YouTube 7/24 Kesintisiz Band Yayını',
                                        'instagram' => '📸 Instagram 7/24 Dikey Band Yayını',
                                        'tiktok' => '🎵 TikTok 7/24 Canlı Yayın Akışı',
                                        default => '🌐 7/24 Çoklu Platform Band Yayını (Simulcast)',
                                    });
                                }

                                if ($state === 'youtube') {
                                    $set('youtube_enabled', true);
                                    $set('instagram_enabled', false);
                                    $set('tiktok_enabled', false);
                                } elseif ($state === 'instagram') {
                                    $set('youtube_enabled', false);
                                    $set('instagram_enabled', true);
                                    $set('tiktok_enabled', false);
                                    $set('resolution_mode', 'vertical_1080p');
                                } elseif ($state === 'tiktok') {
                                    $set('youtube_enabled', false);
                                    $set('instagram_enabled', false);
                                    $set('tiktok_enabled', true);
                                    $set('resolution_mode', 'vertical_1080p');
                                } else {
                                    $set('youtube_enabled', true);
                                    $set('instagram_enabled', true);
                                    $set('tiktok_enabled', true);
                                }
                            }),

                        TextInput::make('title')
                            ->label('Yayın Kanalı Adı')
                            ->default('🔴 YouTube 7/24 Kesintisiz Band Yayını')
                            ->placeholder('Örn: YouTube 7/24 Patenli Ayakkabılar Canlı Yayını')
                            ->required()
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('🎬 Video Havuzu & Band Kaydı Kaynak Ayarları')
                    ->description('Canlı yayına basılacak videoların kaynağını, oynatma sırasını ve filtrelerini belirleyin.')
                    ->schema([
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
                                        'shuffle' => '🔀 Karışık (Rastgele TV Döngüsü)',
                                    ])
                                    ->default('sequential')
                                    ->required(),

                                Select::make('aspect_ratio_filter')
                                    ->label('Video Format Filtresi')
                                    ->options([
                                        'all' => '🌟 Tüm Videolar (Otomatik Ölçekle)',
                                        '9:16' => '📱 Sadece 9:16 Dikey Videolar (Reels/Shorts)',
                                        '16:9' => '🖥️ Sadece 16:9 Yatay Videolar (Yatay YouTube)',
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
                                    ->label('Yayın Çıkış Formatı & Çözünürlük')
                                    ->options([
                                        'vertical_1080p' => '📱 9:16 Dikey 1080p (IG Live, TikTok & YT Shorts Canlı)',
                                        'vertical_720p' => '📱 9:16 Dikey 720p (Düşük CPU / Hızlı Akış)',
                                        'horizontal_1080p' => '🖥️ 16:9 Yatay 1080p (Standart YouTube TV Yayını)',
                                        'horizontal_720p' => '🖥️ 16:9 Yatay 720p (Standart HD)',
                                        'copy' => '⚡ Doğrudan Aktarım (Encode Yok / Ultra Düşük CPU)',
                                    ])
                                    ->default('vertical_1080p')
                                    ->required(),

                                Select::make('bitrate_kbps')
                                    ->label('Video Bitrate (Kalite / Bant Genişliği)')
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

                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_loop')
                                    ->label('♾️ 7/24 Sonsuz Band Döngüsü')
                                    ->helperText('Havuzdaki son video bittiğinde yayın kesilmeden 1. videodan devam eder.')
                                    ->default(true),

                                Toggle::make('auto_restart')
                                    ->label('🛡️ Otomatik Kurtarma (Watchdog)')
                                    ->helperText('İnternet veya RTMP kopmasında yayını otomatik yeniden başlatır.')
                                    ->default(true),
                            ]),
                    ]),

                // 🎵 TELİFSİZ FON MÜZİĞİ & SES AYARLARI (TELİF KORUMASI)
                Section::make('🎵 Telifsiz Fon Müziği & Ses Ayarları (Telif Koruması)')
                    ->description('Canlı yayındaki telifli şarkı veya sesleri engelleyin, arkaya telifsiz kesintisiz fon müziği koyun veya sesleri karıştırın.')
                    ->collapsible()
                    ->schema([
                        Select::make('audio_mode')
                            ->label('Ses Yayın Modu')
                            ->options([
                                'original' => '🔊 Orijinal Video Sesi (Videodaki mevcut sesleri yayınla)',
                                'replace_bg_music' => '🛡️ Telifsiz Müzik ile Değiştir (Telif Önleme — Şiddetle Tavsiye Edilen)',
                                'mix_bg_music' => '🎚️ Orijinal Ses + Telifsiz Müzik Karıştır (Arka Plan Fon Müziği)',
                                'mute' => '🔇 Tamamen Sessiz (Sessiz Canlı Yayın)',
                            ])
                            ->default('original')
                            ->live()
                            ->helperText('Telif hakkı ihtarı yememek için "Telifsiz Müzik ile Değiştir" modunu seçerek videodaki müzikleri telifsiz fon müziği ile değiştirebilirsiniz.'),

                        Grid::make(2)
                            ->visible(fn (Get $get): bool => in_array($get('audio_mode'), ['replace_bg_music', 'mix_bg_music'], true))
                            ->schema([
                                Select::make('bg_music_source')
                                    ->label('Müzik Kaynağı')
                                    ->options([
                                        'preset' => '✨ Hazır Telifsiz Müzik Havuzu (Sistem Kütüphanesi)',
                                        'custom' => '📁 Özel Müzik Dosyası Yükle (Kendi MP3/M4A Dosyanız)',
                                    ])
                                    ->default('preset')
                                    ->live(),

                                Select::make('bg_music_preset')
                                    ->label('Telifsiz Müzik Türü')
                                    ->options([
                                        'lofi_chill' => '🎧 Lo-Fi Chill Beats (Rahatlatıcı & Modern Akorlar)',
                                        'ambient_relax' => '🌅 Ambient Deep Relax (Huzurlu 432Hz Derin Ortam)',
                                        'upbeat_energetic' => '⚡ Upbeat Energetic (Hareketli & Ritmik)',
                                        'acoustic_guitar' => '🎹 Acoustic Melody (Hafif Akustik & Melodik)',
                                    ])
                                    ->default('lofi_chill')
                                    ->visible(fn (Get $get): bool => $get('bg_music_source') !== 'custom')
                                    ->helperText('Bu müzikler matematiksel frekans sentezi ile üretilir; YouTube, Instagram veya TikTok telif sistemlerinde %100 temizdir.'),

                                FileUpload::make('bg_music_path')
                                    ->label('Özel Telifsiz Müzik Dosyası (MP3 / M4A / WAV)')
                                    ->disk('public')
                                    ->directory('audio-library')
                                    ->preserveFilenames()
                                    ->maxSize(102400) // 100 MB
                                    ->acceptedFileTypes([
                                        'audio/*',
                                        'audio/wav',
                                        'audio/x-wav',
                                        'audio/wave',
                                        'audio/vnd.wave',
                                        'audio/x-pn-wav',
                                        'audio/mpeg',
                                        'audio/mp3',
                                        'audio/mpeg3',
                                        'audio/x-mpeg-3',
                                        'audio/aac',
                                        'audio/x-aac',
                                        'audio/m4a',
                                        'audio/x-m4a',
                                        'audio/mp4',
                                        'audio/x-mp4',
                                        'audio/ogg',
                                        'audio/flac',
                                        'audio/x-flac',
                                    ])
                                    ->visible(fn (Get $get): bool => $get('bg_music_source') === 'custom')
                                    ->helperText('Kendi indirdiğiniz telifsiz MP3, WAV veya M4A dosyasını yükleyin. Yayın boyunca kesintisiz döngüde çalacaktır.'),
                            ]),

                        Grid::make(2)
                            ->visible(fn (Get $get): bool => in_array($get('audio_mode'), ['replace_bg_music', 'mix_bg_music'], true))
                            ->schema([
                                TextInput::make('bg_music_volume')
                                    ->label('Fon Müziği Ses Düzeyi (%)')
                                    ->numeric()
                                    ->minValue(5)
                                    ->maxValue(100)
                                    ->default(fn (Get $get) => $get('audio_mode') === 'mix_bg_music' ? 30 : 100)
                                    ->suffix('%')
                                    ->helperText('Mikser modunda %25-%35 tavsiye edilir, tam müzik değişiminde %100 uygundur.'),

                                TextInput::make('original_audio_volume')
                                    ->label('Orijinal Video Sesi Düzeyi (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(100)
                                    ->suffix('%')
                                    ->visible(fn (Get $get): bool => $get('audio_mode') === 'mix_bg_music')
                                    ->helperText('Orijinal videodaki konuşma veya seslerin seviyesi.'),
                            ]),
                    ]),

                // 🔴 1. YOUTUBE CANLI YAYIN AYARLARI
                Section::make('🔴 YouTube Canlı Yayın Ayarları & Bağlantısı')
                    ->description('YouTube Studio Canlı Yayın RTMP bilgileri veya bağlı Google OAuth hesabı ile otomatik yayın.')
                    ->visible(fn (Get $get): bool => in_array($get('target_platform'), ['youtube', 'multi'], true))
                    ->schema([
                        Toggle::make('youtube_enabled')
                            ->label('YouTube Canlı Yayınını Etkinleştir')
                            ->default(true)
                            ->live()
                            ->visible(fn (Get $get): bool => $get('target_platform') === 'multi'),

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
                                    ->helperText('YouTube Studio > Canlı Yayın > Yayın Anahtarı alanından kopyalayın veya aşağıdaki butondan otomatik oluşturun.')
                                    ->required(fn (Get $get): bool => $get('target_platform') === 'youtube' || (bool) $get('youtube_enabled')),
                            ]),

                        Select::make('youtube_account_id')
                            ->label('Bağlı YouTube Kanalı (API ile Otomatik Yayın Oluşturmak İçin - İsteğe Bağlı)')
                            ->options(fn () => SocialAccount::with('platform')
                                ->whereHas('platform', fn ($q) => $q->where('code', 'youtube'))
                                ->get()
                                ->mapWithKeys(fn ($a) => [$a->id => "▶️ {$a->name} (@{$a->username})"])
                                ->toArray())
                            ->placeholder('Manuel RTMP Anahtarı kullanıyorsanız boş bırakabilirsiniz')
                            ->columnSpanFull(),

                        Placeholder::make('yt_guide')
                            ->hiddenLabel()
                            ->content(new HtmlString('
                                <div class="p-3 rounded-lg border border-red-500/30 bg-red-950/20 text-xs text-red-200 leading-relaxed">
                                    <strong class="block text-red-400 mb-1">💡 YouTube Canlı Yayın İpuçları:</strong>
                                    • <strong>Dikey (Shorts) Canlı Yayın:</strong> Formatı <strong>9:16 Dikey (1080p)</strong> seçtiğinizde yayınınız YouTube mobil uygulamasında Shorts akışında doğrudan canlı yayın olarak öne çıkarılır!<br>
                                    • <strong>Yatay TV Yayını:</strong> Klasik YouTube yayını için formatı <strong>16:9 Yatay (1080p)</strong> olarak ayarlayabilirsiniz.<br>
                                    • Tablodaki <strong>🔑 Otomatik Anahtar Al</strong> butonuna basarak bağlı YouTube kanalınızdan otomatik yayın oturumu ve anahtarı türetebilirsiniz.
                                </div>
                            '))
                            ->columnSpanFull(),
                    ]),

                // 📸 2. INSTAGRAM CANLI YAYIN AYARLARI
                Section::make('📸 Instagram Canlı Yayın Ayarları (Instagram Live Producer RTMPS)')
                    ->description('Bilgisayardan / Sunucudan Instagram hesabınızda dikey canlı yayın yapmak için Live Producer RTMPS bilgileri.')
                    ->visible(fn (Get $get): bool => in_array($get('target_platform'), ['instagram', 'multi'], true))
                    ->schema([
                        Toggle::make('instagram_enabled')
                            ->label('Instagram Canlı Yayınını Etkinleştir')
                            ->default(fn (Get $get) => $get('target_platform') === 'instagram')
                            ->live()
                            ->visible(fn (Get $get): bool => $get('target_platform') === 'multi'),

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
                                    ->required(fn (Get $get): bool => $get('target_platform') === 'instagram' || (bool) $get('instagram_enabled')),
                            ]),

                        Placeholder::make('ig_live_guide')
                            ->hiddenLabel()
                            ->content(new HtmlString('
                                <div class="p-3 rounded-lg border border-pink-500/30 bg-pink-950/20 text-xs text-pink-200 leading-relaxed">
                                    <strong class="block text-pink-400 mb-1">💡 Instagram Yayın Anahtarı (Stream Key) Nasıl Alınır?</strong>
                                    1. Bilgisayardan tarayıcınızla <strong>instagram.com</strong> adresine girin.<br>
                                    2. Sol menüdeki <strong>+ Oluştur (Create) → Canlı Video (Live video)</strong> butonuna tıklayın.<br>
                                    3. Başlığı yazıp hedef kitleyi <strong>Herkese Açık (Public)</strong> seçin.<br>
                                    4. Ekranda beliren <strong>Yayın URL\'si (Stream URL)</strong> ve <strong>Yayın Anahtarı (Stream Key)</strong> bilgilerini buraya yapıştırıp <strong>Yayını Başlat</strong>\'a basın.<br>
                                    5. Video Instagram önizleme ekranına düştüğünde Instagram\'daki mavi <strong>"Canlı Yayına Geç (Go Live)"</strong> butonuna tıklayın!
                                </div>
                            '))
                            ->columnSpanFull(),
                    ]),

                // 🎵 3. TIKTOK CANLI YAYIN AYARLARI
                Section::make('🎵 TikTok Canlı Yayın Ayarları (TikTok Live Studio / RTMP)')
                    ->description('TikTok hesabınızda 7/24 kesintisiz dikey canlı yayın yapmak için Live Studio / RTMP bilgileri.')
                    ->visible(fn (Get $get): bool => in_array($get('target_platform'), ['tiktok', 'multi'], true))
                    ->schema([
                        Toggle::make('tiktok_enabled')
                            ->label('TikTok Canlı Yayınını Etkinleştir')
                            ->default(fn (Get $get) => $get('target_platform') === 'tiktok')
                            ->live()
                            ->visible(fn (Get $get): bool => $get('target_platform') === 'multi'),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('tiktok_rtmp_url')
                                    ->label('TikTok RTMP Sunucu Adresi (Server URL)')
                                    ->default('rtmp://live-push.tiktok.com/live/')
                                    ->placeholder('rtmp://live-push.tiktok.com/live/')
                                    ->required(fn (Get $get): bool => (bool) $get('tiktok_enabled')),

                                TextInput::make('tiktok_stream_key')
                                    ->label('TikTok Yayın Anahtarı (Stream Key)')
                                    ->password()
                                    ->revealable()
                                    ->placeholder('Örn: ?trans_code=0&trans_id=...')
                                    ->helperText('TikTok Live Center veya Live Studio uygulamasından temin edilen yayın anahtarı.')
                                    ->required(fn (Get $get): bool => $get('target_platform') === 'tiktok' || (bool) $get('tiktok_enabled')),
                            ]),

                        Select::make('tiktok_account_id')
                            ->label('Bağlı TikTok Hesabı (İsteğe Bağlı)')
                            ->options(fn () => SocialAccount::with('platform')
                                ->whereHas('platform', fn ($q) => $q->where('code', 'tiktok'))
                                ->get()
                                ->mapWithKeys(fn ($a) => [$a->id => "🎵 {$a->name} (@{$a->username})"])
                                ->toArray())
                            ->placeholder('Hesap seçimi isteğe bağlıdır')
                            ->columnSpanFull(),

                        Placeholder::make('tiktok_guide')
                            ->hiddenLabel()
                            ->content(new HtmlString('
                                <div class="p-3 rounded-lg border border-cyan-500/30 bg-cyan-950/20 text-xs text-cyan-200 leading-relaxed">
                                    <strong class="block text-cyan-400 mb-1">💡 TikTok Yayın Anahtarı (Stream Key) Nasıl Alınır?</strong>
                                    1. Bilgisayarınızda <strong>TikTok Live Studio</strong> programını veya <strong>tiktok.com/live/creators</strong> sayfasını açın.<br>
                                    2. <strong>Canlı Yayını Başlat</strong> ekranında <strong>"Özel RTMP / Yayın Bilgileri"</strong> seçeneğini bulun.<br>
                                    3. Size verilen <strong>Sunucu URL\'si</strong> ve <strong>Yayın Anahtarı</strong> değerlerini yukarıdaki kutulara yapıştırın.<br>
                                    4. SocialFlow\'dan <strong>Yayını Başlat</strong>\'a basın ve TikTok üzerinden yayınınızı canlıya geçirin!<br>
                                    <em>Not: TikTok Live RTMP için hesabınızda canlı yayın izninin (Live Studio erişimi veya 1.000 takipçi) açık olması gerekir.</em>
                                </div>
                            '))
                            ->columnSpanFull(),
                    ]),

                // 🌐 4. EK ÖZEL RTMP HEDEFİ
                Section::make('🌐 Ek Özel RTMP Hedefi (Facebook Live / Kick / Twitch / Restream)')
                    ->description('Aynı anda Facebook Live veya diğer platformlara da yayın göndermek isterseniz açabilirsiniz.')
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
                                    ->placeholder('Örn: Facebook Live / Twitch'),

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
                    ->grow(false)
                    ->color(fn (LiveStream $record): string => match ($record->status) {
                        'running' => 'success',
                        'starting', 'restarting' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (LiveStream $record): string => match ($record->status) {
                        'running' => '🟢 Canlı',
                        'starting' => '⏳ Başlatılıyor',
                        'restarting' => '🔄 Yeniden',
                        'error' => '⚠️ Hata',
                        default => '⏹️ Durduruldu',
                    })
                    ->description(fn (LiveStream $record): ?string => $record->status === 'running' && $record->process_pid
                        ? "PID: {$record->process_pid} • {$record->uptime_formatted}"
                        : ($record->last_error ? mb_substr($record->last_error, 0, 36) . '...' : null)
                    )
                    ->wrap(),

                TextColumn::make('target_platform')
                    ->label('Platform')
                    ->badge()
                    ->grow(false)
                    ->size(TextColumnSize::ExtraSmall)
                    ->color(fn (?string $state): string => match ($state) {
                        'youtube' => 'danger',
                        'instagram' => 'warning',
                        'tiktok' => 'info',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'youtube' => '🔴 YouTube Live',
                        'instagram' => '📸 Instagram Live',
                        'tiktok' => '🎵 TikTok Live',
                        default => '🌐 Çoklu Platform',
                    })
                    ->description(fn (LiveStream $record): string => "🎬 {$record->getEligibleVideos()->count()} Video ({$record->formatted_loop_duration})"),

                TextColumn::make('targets')
                    ->label('Hedefler')
                    ->wrap()
                    ->getStateUsing(function (LiveStream $record): HtmlString {
                        $badges = [];
                        if ($record->youtube_enabled) {
                            $hasKey = ! empty(trim((string) $record->youtube_stream_key));
                            $badges[] = '<span style="display:inline-flex;align-items:center;gap:3px;padding:2px 6px;border-radius:4px;font-size:10.5px;font-weight:600;background:rgba(239,68,68,0.18);color:#fca5a5;border:1px solid rgba(239,68,68,0.35);white-space:nowrap;">▶️ YT ' . ($hasKey ? '✓' : '⚠️') . '</span>';
                        }
                        if ($record->instagram_enabled) {
                            $hasKey = ! empty(trim((string) $record->instagram_stream_key));
                            $badges[] = '<span style="display:inline-flex;align-items:center;gap:3px;padding:2px 6px;border-radius:4px;font-size:10.5px;font-weight:600;background:rgba(236,72,153,0.18);color:#f9a8d4;border:1px solid rgba(236,72,153,0.35);white-space:nowrap;">📸 IG ' . ($hasKey ? '✓' : '⚠️') . '</span>';
                        }
                        if ($record->tiktok_enabled) {
                            $hasKey = ! empty(trim((string) $record->tiktok_stream_key));
                            $badges[] = '<span style="display:inline-flex;align-items:center;gap:3px;padding:2px 6px;border-radius:4px;font-size:10.5px;font-weight:600;background:rgba(6,182,212,0.18);color:#67e8f9;border:1px solid rgba(6,182,212,0.35);white-space:nowrap;">🎵 TikTok ' . ($hasKey ? '✓' : '⚠️') . '</span>';
                        }
                        if ($record->custom_rtmp_enabled) {
                            $name = e($record->custom_rtmp_name ?: 'Özel RTMP');
                            $badges[] = '<span style="display:inline-flex;align-items:center;gap:3px;padding:2px 6px;border-radius:4px;font-size:10.5px;font-weight:600;background:rgba(99,102,241,0.18);color:#c7d2fe;border:1px solid rgba(99,102,241,0.35);white-space:nowrap;">🌐 ' . $name . '</span>';
                        }

                        if (empty($badges)) {
                            return new HtmlString('<span class="text-xs text-gray-500">Hedef seçilmedi</span>');
                        }

                        return new HtmlString('<div class="flex flex-wrap gap-1">' . implode('', $badges) . '</div>');
                    }),

                TextColumn::make('resolution_mode')
                    ->label('Format')
                    ->grow(false)
                    ->badge()
                    ->size(TextColumnSize::ExtraSmall)
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'vertical_1080p' => '9:16 (1080p)',
                        'vertical_720p' => '9:16 (720p)',
                        'horizontal_1080p' => '16:9 (1080p)',
                        'horizontal_720p' => '16:9 (720p)',
                        'copy' => '⚡ Copy',
                        default => (string) $state,
                    })
                    ->description(fn (LiveStream $record): string => "{$record->bitrate_kbps}k • {$record->fps}fps")
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('target_platform')
                    ->label('Platform Filtresi')
                    ->options([
                        'youtube' => '🔴 YouTube',
                        'instagram' => '📸 Instagram',
                        'tiktok' => '🎵 TikTok',
                        'multi' => '🌐 Çoklu (Simulcast)',
                    ]),
            ])
            ->actions([
                Action::make('start_stream')
                    ->label('Başlat')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->button()
                    ->size('xs')
                    ->visible(fn (LiveStream $record): bool => $record->status !== 'running')
                    ->requiresConfirmation()
                    ->modalHeading(fn (LiveStream $record): string => "🔴 Canlı Yayını Başlat: {$record->title}")
                    ->modalDescription('Video havuzundaki videolar birleştirilerek seçili platforma 7/24 band kaydı olarak canlı yayınlanmaya başlayacaktır.')
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
                    ->size('xs')
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

                Action::make('monitor')
                    ->label('Monitör')
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

                Tables\Actions\ActionGroup::make([
                    Action::make('restart_stream')
                        ->label('Yeniden Başlat')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
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

                    Tables\Actions\DeleteAction::make()
                        ->before(function (LiveStream $record) {
                            if ($record->status === 'running') {
                                app(LiveStreamService::class)->stopStream($record);
                            }
                        }),
                ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('Diğer İşlemler'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLiveStreams::route('/'),
        ];
    }
}
