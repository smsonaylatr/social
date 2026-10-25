<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\SocialAccount;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Yayın ve Otomasyon';
    protected static ?string $navigationLabel = 'Otomatik Yayın Kuralları';
    protected static ?string $modelLabel = 'Otomasyon Kuralı';
    protected static ?string $pluralModelLabel = 'Otomatik Yayın Kuralları';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Zamanlayıcı ve Video Kaynağı')
                    ->schema([
                        TextInput::make('name')
                            ->label('Otomasyon Adı')
                            ->placeholder('Örn: Patenli Ayakkabılar Gün İçi Çoklu Paylaşım')
                            ->required()
                            ->maxLength(255),

                        Select::make('playlist_id')
                            ->label('Video Kaynağı')
                            ->options(Playlist::pluck('name', 'id'))
                            ->placeholder('Tüm Video Havuzundan Otomatik Sırayla Seç (1 → 2 → 3)')
                            ->helperText('Boş bırakırsanız Video Havuzuna yüklediğiniz videolar sırayla (1 → 2 → 3) otomatik paylaşılır.')
                            ->searchable(),

                        Select::make('schedule_type')
                            ->label('Günlük Paylaşım Düzeni / Sıklığı')
                            ->options([
                                'algorithmic_growth' => '🚀 Algoritmik Keşfet & Organik Büyüme (Platforma Özel Zirve Saatler + Anti-Spam)',
                                'hourly' => '🕐 Her Saat Başı Paylaş (00:00 - 23:00 / Gün Boyu Her Saat)',
                                'minute_interval' => '⚡ ** Dakikada Bir Paylaş (Örn: Her 15, 30, 45 Dakikada Bir)',
                                'interval' => '⏳ Belirli Saat Aralıklarıyla (Örn: Her 2 veya 3 Saatte Bir)',
                                'custom_times' => '⏰ Belirli Saatlerde Paylaş (Seçilen Özel Saatlerde)',
                                'single' => '🎯 Günde 1 Kez (Belirli Tek Saatte)',
                            ])
                            ->default('algorithmic_growth')
                            ->live()
                            ->required(),

                        // 0. Algoritmik Keşfet ve Büyüme Modu Alanları
                        Section::make('🚀 Algoritmik Keşfet & Anti-Spam Kalkanı')
                            ->description('Sosyal ağların bot algoritmalarını aşarak organik izlenmeyi ve keşfete çıkmayı maksimize eden akıllı zamanlayıcı.')
                            ->schema([
                                Select::make('growth_strategy')
                                    ->label('Büyüme & Dalga Stratejisi')
                                    ->options([
                                        'max_capacity' => '🔥 Maksimum Güç & Tam Kapasite (Günde 5 Dalga / Platformların TÜM Günlük Haklarını Kullan)',
                                        'balanced' => '⚡ Dengeli Organik Keşfet (Günde 3 Dalga / Öğle, İkindi ve Akşam Prime)',
                                        'conservative' => '🛡️ Hafif & Güvenli (Günde 2 Dalga / Öğle ve Akşam Zirvesi)',
                                    ])
                                    ->default('max_capacity')
                                    ->required(fn (Get $get): bool => $get('schedule_type') === 'algorithmic_growth'),

                                Grid::make(3)
                                    ->schema([
                                        Toggle::make('anti_spam_shield')
                                            ->label('🛡️ Algoritmik Soğuma (Cooldown)')
                                            ->helperText('Platformun spam sınırından önce peş peşe post atılmasını engeller.')
                                            ->default(true),

                                        Toggle::make('enable_smart_jitter')
                                            ->label('🎲 Akıllı Organik Sapma (Jitter)')
                                            ->helperText('Tam saat yerine ±3-12 dk rastgele insan davranışı ekler.')
                                            ->default(true),

                                        Toggle::make('enable_staggered_dispatch')
                                            ->label('⏳ Kademeli Çapraz Dağıtım')
                                            ->helperText('Tüm mecralara aynı anda değil, 6-12 dk arayla yükler.')
                                            ->default(true),
                                    ]),

                                Placeholder::make('algo_guide')
                                    ->label('Platform Algoritma Kılavuzu & Zirve Keşfet Saatleri')
                                    ->content(new HtmlString(
                                        '<div class="mb-3 p-3 rounded-lg border border-emerald-500/40 bg-emerald-950/20 text-emerald-300 text-xs leading-relaxed">' .
                                        '<strong>💡 Maksimum Güç & Akıllı Hak Dağıtımı:</strong> Her platformun günlük tüm hakları (Instagram 5, TikTok 5, YouTube 5, Facebook 5, X 5, LinkedIn 4, Pinterest 4) spam cezasına takılmadan sonuna kadar kullanılır. Kademeli yükleme (stagger) ve anti-spam jitter ile hesaplar koruma altındadır.' .
                                        '</div>' .
                                        '<div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs mt-2">' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60"><strong class="text-pink-400">📸 Instagram Reels (Günde 5 Video):</strong> 09:30 • 12:45 • 16:15 • 19:15 • 22:00<br><span class="text-gray-400">İlk 45 dk tamamlama testi. Kademeli dağıtımla shadowban riski sıfır.</span></div>' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60"><strong class="text-cyan-400">🎵 TikTok FYP (Günde 5 Video):</strong> 09:30 • 12:45 • 16:15 • 19:15 • 22:00<br><span class="text-gray-400">FYP batch testi. Günde 5 video ile maksimum viral şansı.</span></div>' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60"><strong class="text-red-400">▶️ YouTube Shorts (Günde 5 Video):</strong> 09:30 • 12:45 • 16:15 • 19:15 • 22:00<br><span class="text-gray-400">Shorts rafına 2-4 saatte girer. Günde 5 video güvenli aralıklarla dağıtılır.</span></div>' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60"><strong class="text-blue-400">👥 Facebook Reels (Günde 5 Video):</strong> 09:30 • 12:45 • 16:15 • 19:15 • 22:00<br><span class="text-gray-400">Facebook Reels ve sayfa akışı için 5 zirve dalgası.</span></div>' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60"><strong class="text-gray-300">🐦 X (Twitter) (Günde 5 Tweet):</strong> 09:30 • 12:45 • 16:15 • 19:15 • 22:00<br><span class="text-gray-400">Gündem ve etkileşim zirveleri.</span></div>' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60"><strong class="text-red-500">📌 Pinterest (Günde 4 Pin):</strong> 12:45 • 16:15 • 19:15 • 22:00<br><span class="text-gray-400">Görsel arama ve pin kaydetme zirveleri.</span></div>' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60 col-span-full"><strong class="text-blue-500">💼 LinkedIn (Günde 4 Post):</strong> 09:00 • 12:30 • 16:45 • 19:30<br><span class="text-gray-400">Hafta içi ve hafta sonu tam aktif. Mesai başı, öğle molası, paydos ve akşam ağ saatleri.</span></div>' .
                                        '</div>'
                                    ))
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn (Get $get): bool => $get('schedule_type') === 'algorithmic_growth')
                            ->columnSpanFull(),

                        // 1. Tek saat modu
                        TimePicker::make('time_of_day')
                            ->label('Günlük Paylaşım Saati')
                            ->default('12:00:00')
                            ->seconds(false)
                            ->visible(fn (Get $get): bool => $get('schedule_type') === 'single')
                            ->required(fn (Get $get): bool => $get('schedule_type') === 'single'),

                        // 2. Her saat başı modu
                        Grid::make(2)
                            ->schema([
                                TimePicker::make('start_time')
                                    ->label('Günün İlk Paylaşım Saati')
                                    ->default('00:00:00')
                                    ->seconds(false)
                                    ->helperText('Tüm gün için 00:00 bırakabilirsiniz.')
                                    ->required(fn (Get $get): bool => $get('schedule_type') === 'hourly'),

                                TimePicker::make('end_time')
                                    ->label('Günün Bitiş Saati')
                                    ->default('23:59:00')
                                    ->seconds(false)
                                    ->helperText('Tüm gün için 23:59 seçili kalabilir.')
                                    ->required(fn (Get $get): bool => $get('schedule_type') === 'hourly'),
                            ])
                            ->visible(fn (Get $get): bool => $get('schedule_type') === 'hourly'),

                        // 3. ** Dakikada bir paylaşım modu
                        Grid::make(3)
                            ->schema([
                                Select::make('interval_minutes')
                                    ->label('Paylaşım Sıklığı (Dakika)')
                                    ->options([
                                        5 => 'Her 5 Dakikada Bir',
                                        10 => 'Her 10 Dakikada Bir',
                                        15 => 'Her 15 Dakikada Bir',
                                        20 => 'Her 20 Dakikada Bir',
                                        30 => 'Her 30 Dakikada Bir',
                                        45 => 'Her 45 Dakikada Bir',
                                        60 => 'Her 60 Dakikada Bir (1 Saat)',
                                        90 => 'Her 90 Dakikada Bir (1.5 Saat)',
                                        120 => 'Her 120 Dakikada Bir (2 Saat)',
                                    ])
                                    ->default(30)
                                    ->required(fn (Get $get): bool => $get('schedule_type') === 'minute_interval'),

                                TimePicker::make('start_time')
                                    ->label('İlk Başlangıç Saati')
                                    ->default('00:00:00')
                                    ->seconds(false)
                                    ->required(fn (Get $get): bool => $get('schedule_type') === 'minute_interval'),

                                TimePicker::make('end_time')
                                    ->label('Günün Bitiş Saati')
                                    ->default('23:59:00')
                                    ->seconds(false)
                                    ->required(fn (Get $get): bool => $get('schedule_type') === 'minute_interval'),
                            ])
                            ->visible(fn (Get $get): bool => $get('schedule_type') === 'minute_interval'),

                        // 4. Özel saatler modu
                        TagsInput::make('posting_times')
                            ->label('Gün İçi Paylaşım Saatleri')
                            ->placeholder('Saat yazıp Enter tuşuna basın (Örn: 10:00, 14:00, 18:00, 22:00)')
                            ->suggestions([
                                '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00',
                                '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00',
                                '22:00', '23:00',
                            ])
                            ->default(['10:00', '14:00', '18:00', '22:00'])
                            ->helperText('Gün içinde paylaşım yapılmasını istediğiniz saatleri ekleyin. Aşağıdaki önerilen saatlere tıklayabilir veya özel saat yazıp Enter tuşuna basabilirsiniz.')
                            ->visible(fn (Get $get): bool => $get('schedule_type') === 'custom_times')
                            ->required(fn (Get $get): bool => $get('schedule_type') === 'custom_times'),

                        // 5. Belirli saat aralıkları modu
                        Grid::make(3)
                            ->schema([
                                TimePicker::make('start_time')
                                    ->label('İlk Paylaşım Saati')
                                    ->default('00:00:00')
                                    ->seconds(false)
                                    ->required(fn (Get $get): bool => $get('schedule_type') === 'interval'),

                                TimePicker::make('end_time')
                                    ->label('Günün Bitiş Saati')
                                    ->default('23:59:00')
                                    ->seconds(false)
                                    ->required(fn (Get $get): bool => $get('schedule_type') === 'interval'),

                                Select::make('interval_hours')
                                    ->label('Paylaşım Aralığı')
                                    ->options([
                                        1 => 'Her 1 Saatte Bir',
                                        2 => 'Her 2 Saatte Bir',
                                        3 => 'Her 3 Saatte Bir',
                                        4 => 'Her 4 Saatte Bir',
                                        6 => 'Her 6 Saatte Bir',
                                        8 => 'Her 8 Saatte Bir',
                                    ])
                                    ->default(2)
                                    ->required(fn (Get $get): bool => $get('schedule_type') === 'interval'),
                            ])
                            ->visible(fn (Get $get): bool => $get('schedule_type') === 'interval'),

                        CheckboxList::make('days_of_week')
                            ->label('Yayın Günleri')
                            ->options([
                                1 => 'Pazartesi',
                                2 => 'Salı',
                                3 => 'Çarşamba',
                                4 => 'Perşembe',
                                5 => 'Cuma',
                                6 => 'Cumartesi',
                                7 => 'Pazar',
                            ])
                            ->default([1, 2, 3, 4, 5, 6, 7])
                            ->columns(4)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Hedef Sosyal Medya Hesapları ve Kural Durumu')
                    ->schema([
                        CheckboxList::make('target_social_account_ids')
                            ->label('Yayın Yapılacak Hesaplar')
                            ->options(SocialAccount::all()->mapWithKeys(fn ($a) => [$a->id => "{$a->platform->name} - {$a->name} ({$a->account_type})"]))
                            ->columns(2)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Otomasyonu Etkinleştir')
                            ->helperText('Etkinleştirildiğinde zamanı gelen paylaşımlar otomatik olarak kuyruğa aktarılır.')
                            ->default(true),
                    ]),
            ]);
    }

    public static function formatPlatformName(string $name): string
    {
        $clean = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $name);
        $clean = preg_replace('/\s*\(Günde\s*\d+\s*Dalga\)/iu', '', $clean);
        $clean = preg_replace('/\s*(Keşfet|Algoritma|Zirve Etkileşim|Profesyonel Ağ|Pin & Keşfet|Reels & Akış|FYP Viral)\s*Kuralı/iu', '', $clean);
        $clean = trim($clean);

        $lower = mb_strtolower($name);
        if (str_contains($lower, 'instagram')) {
            return 'Instagram Reels';
        }
        if (str_contains($lower, 'youtube')) {
            return 'YouTube Shorts';
        }
        if (str_contains($lower, 'tiktok')) {
            return 'TikTok';
        }
        if (str_contains($lower, 'facebook')) {
            return 'Facebook';
        }
        if (str_contains($lower, 'twitter') || str_contains($lower, ' x ') || str_contains($lower, 'x (') || str_starts_with($lower, 'x ') || str_contains($name, '🪶') || str_contains($name, '🐦') || $lower === 'x') {
            return 'X (Twitter)';
        }
        if (str_contains($lower, 'linkedin')) {
            return 'LinkedIn';
        }
        if (str_contains($lower, 'pinterest')) {
            return 'Pinterest';
        }

        return $clean ?: $name;
    }

    public static function renderPlatformBadge(string $name): HtmlString
    {
        $title = static::formatPlatformName($name);
        $lower = mb_strtolower($name);

        if (str_contains($lower, 'instagram')) {
            $bg = 'background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%);';
            $svg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="#ffffff"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>';
        } elseif (str_contains($lower, 'youtube')) {
            $bg = 'background: #FF0000;';
            $svg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="#ffffff"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>';
        } elseif (str_contains($lower, 'tiktok')) {
            $bg = 'background: #09090b; border: 1px solid rgba(255,255,255,0.18);';
            $svg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M13.1 1.5h3.4c.2 1.5.9 2.9 2 3.8 1 .9 2.3 1.4 3.7 1.6v3.4c-1.3-.1-2.6-.4-3.7-1-.5-.2-1-.6-1.4-.9v7.8c-.1 1.3-.5 2.5-1.2 3.5-1.2 1.7-3.2 2.8-5.3 2.9-1.3.1-2.5-.3-3.6-.9-1.8-1.1-3.1-3-3.2-5.1 0-.4 0-.9 0-1.3.2-1.7 1-3.3 2.3-4.4 1.5-1.3 3.5-1.9 5.5-1.5v3.9c-.9-.3-1.9-.2-2.7.3-.6.4-1 .9-1.2 1.6-.2.5-.2 1-.1 1.4.2 1.5 1.6 2.7 3.1 2.6 1 0 1.9-.6 2.5-1.4.2-.3.3-.6.4-.9.1-1.6.1-3.2.1-4.8V1.5z" fill="#25F4EE" transform="translate(-0.6, -0.4)"/><path d="M13.1 1.5h3.4c.2 1.5.9 2.9 2 3.8 1 .9 2.3 1.4 3.7 1.6v3.4c-1.3-.1-2.6-.4-3.7-1-.5-.2-1-.6-1.4-.9v7.8c-.1 1.3-.5 2.5-1.2 3.5-1.2 1.7-3.2 2.8-5.3 2.9-1.3.1-2.5-.3-3.6-.9-1.8-1.1-3.1-3-3.2-5.1 0-.4 0-.9 0-1.3.2-1.7 1-3.3 2.3-4.4 1.5-1.3 3.5-1.9 5.5-1.5v3.9c-.9-.3-1.9-.2-2.7.3-.6.4-1 .9-1.2 1.6-.2.5-.2 1-.1 1.4.2 1.5 1.6 2.7 3.1 2.6 1 0 1.9-.6 2.5-1.4.2-.3.3-.6.4-.9.1-1.6.1-3.2.1-4.8V1.5z" fill="#FE2C55" transform="translate(0.6, 0.4)"/><path d="M13.1 1.5h3.4c.2 1.5.9 2.9 2 3.8 1 .9 2.3 1.4 3.7 1.6v3.4c-1.3-.1-2.6-.4-3.7-1-.5-.2-1-.6-1.4-.9v7.8c-.1 1.3-.5 2.5-1.2 3.5-1.2 1.7-3.2 2.8-5.3 2.9-1.3.1-2.5-.3-3.6-.9-1.8-1.1-3.1-3-3.2-5.1 0-.4 0-.9 0-1.3.2-1.7 1-3.3 2.3-4.4 1.5-1.3 3.5-1.9 5.5-1.5v3.9c-.9-.3-1.9-.2-2.7.3-.6.4-1 .9-1.2 1.6-.2.5-.2 1-.1 1.4.2 1.5 1.6 2.7 3.1 2.6 1 0 1.9-.6 2.5-1.4.2-.3.3-.6.4-.9.1-1.6.1-3.2.1-4.8V1.5z" fill="#FFFFFF"/></svg>';
        } elseif (str_contains($lower, 'facebook')) {
            $bg = 'background: #1877F2;';
            $svg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="#ffffff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';
        } elseif (str_contains($lower, 'twitter') || str_contains($lower, ' x ') || str_contains($lower, 'x (') || str_starts_with($lower, 'x ') || str_contains($name, '🪶') || str_contains($name, '🐦') || $lower === 'x') {
            $bg = 'background: #000000; border: 1px solid rgba(255,255,255,0.2);';
            $svg = '<svg width="11" height="11" viewBox="0 0 24 24" fill="#ffffff"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
        } elseif (str_contains($lower, 'linkedin')) {
            $bg = 'background: #0A66C2;';
            $svg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="#ffffff"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>';
        } elseif (str_contains($lower, 'pinterest')) {
            $bg = 'background: #E60023;';
            $svg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="#ffffff"><path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>';
        } else {
            $bg = 'background: #475569;';
            $svg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2"><path d="M12 2v20M2 12h20"/></svg>';
        }

        return new HtmlString(
            '<div style="display: inline-flex; align-items: center; gap: 0.5rem; vertical-align: middle;">' .
                '<div style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; ' . $bg . ' box-shadow: 0 1px 3px rgba(0,0,0,0.3);">' .
                    $svg .
                '</div>' .
                '<span style="font-weight: 600; font-size: 0.8125rem; white-space: nowrap;">' . e($title) . '</span>' .
            '</div>'
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['playlist']))
            ->columns([
                ViewColumn::make('name')
                    ->label('Platform')
                    ->view('filament.columns.platform-badge')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('playlist.name')
                    ->label('Kaynak')
                    ->default('Tüm Havuz')
                    ->badge()
                    ->color('gray')
                    ->size(TextColumnSize::ExtraSmall)
                    ->visibleFrom('sm')
                    ->sortable(),

                TextColumn::make('schedule_type')
                    ->label('Yayın Sıklığı & Saatler')
                    ->badge()
                    ->color(fn (Schedule $record): string => $record->schedule_type === 'algorithmic_growth' ? 'success' : 'info')
                    ->size(TextColumnSize::ExtraSmall)
                    ->formatStateUsing(function (Schedule $record): string {
                        if ($record->schedule_type === 'algorithmic_growth') {
                            $times = $record->getDailyRunTimes();
                            $waveCount = count($times);
                            return "🚀 Keşfet ({$waveCount} Dalga)";
                        }
                        if ($record->schedule_type === 'hourly') {
                            return '🕐 Saat Başı';
                        }
                        if ($record->schedule_type === 'minute_interval') {
                            return "⚡ Her {$record->interval_minutes} dk";
                        }
                        if ($record->schedule_type === 'interval') {
                            return "⏳ Her {$record->interval_hours} saat";
                        }
                        if ($record->schedule_type === 'custom_times') {
                            return '🎯 Özel Saat';
                        }
                        return '📌 Günde 1 kez';
                    })
                    ->description(function (Schedule $record): string {
                        $times = $record->getDailyRunTimes();
                        return implode(' • ', $times);
                    }),

                TextColumn::make('last_run_at')
                    ->label('Son Çalışma')
                    ->dateTime('d.m.Y H:i')
                    ->size(TextColumnSize::ExtraSmall)
                    ->placeholder('—')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->actionsColumnLabel('İşlemler')
            ->actions([
                Tables\Actions\Action::make('run_now')
                    ->label('Şimdi Paylaş')
                    ->icon('heroicon-o-play')
                    ->iconButton()
                    ->size('sm')
                    ->tooltip('Sıradaki Videoyu Şimdi Paylaş')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Sıradaki Videoyu Şimdi Paylaş')
                    ->modalDescription('Video havuzundan sıradaki video seçilecek ve seçili tüm sosyal medya kanallarına anında yüklenecektir. Devam edilsin mi?')
                    ->action(function (Schedule $record) {
                        $record->update(['is_active' => true]);
                        $post = app(\App\Services\Automation\AutomationOrchestratorService::class)->runSchedule($record);
                        if ($post) {
                            try {
                                \Illuminate\Support\Facades\Artisan::call('queue:work', [
                                    '--stop-when-empty' => true,
                                    '--max-jobs' => 20,
                                    '--tries' => 3,
                                ]);
                            } catch (\Throwable) {}

                            $post->refresh();
                            $publishedCount = $post->targets()->where('status', \App\Enums\PublishStatus::PUBLISHED)->count();
                            $failedTargets = $post->targets()->where('status', \App\Enums\PublishStatus::FAILED)->get();

                            if ($publishedCount > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Video Başarıyla Paylaşıldı! 🚀')
                                    ->body("Video: '{$post->video?->title}' ({$publishedCount} kanala yüklendi)")
                                    ->success()
                                    ->duration(8000)
                                    ->send();
                            } elseif ($failedTargets->isNotEmpty()) {
                                $firstErr = $failedTargets->first()->error_message ?: 'API hatası';
                                \Filament\Notifications\Notification::make()
                                    ->title('Paylaşım Sırasında Hata Oluştu')
                                    ->body("Kanal Hatası: {$firstErr}")
                                    ->danger()
                                    ->duration(10000)
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Sıradaki Video Kuyruğa Alındı')
                                    ->body("Seçilen video: {$post->video?->title}")
                                    ->info()
                                    ->send();
                            }
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Paylaşım Başlatılamadı')
                                ->body('Uygun video bulunamadı veya bu kuralda hedef sosyal medya hesabı seçilmemiş.')
                                ->warning()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->size('sm')
                    ->tooltip('Düzenle')
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::ThreeExtraLarge),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->size('sm')
                    ->tooltip('Sil'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSchedules::route('/'),
        ];
    }
}
