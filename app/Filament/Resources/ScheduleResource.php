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
use Filament\Tables\Columns\ToggleColumn;
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
                                        '<strong>💡 Maksimum Güç & Akıllı Hak Dağıtımı:</strong> Tek bir kural oluşturduğunuzda sistem günde 5 algoritmik dalga çalıştırır. Her platform kendi maksimum günlük hakkı kadar içeriğe dahil edilir. Örneğin TikTok günde 4-5 video yayınlarken, YouTube Shorts algoritması spam cezası vermemesi için günde 2 Shorts yayınlar. Böylece tüm platformların günlük hakları sıfır spam riskiyle sonuna kadar tüketilir!' .
                                        '</div>' .
                                        '<div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs mt-2">' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60"><strong class="text-pink-400">📸 Instagram Reels (Günde 3 Video):</strong> 12:45 • 19:15 • 22:00<br><span class="text-gray-400">İlk 45 dk tamamlama oranı testi. Min 3 saat soğuma kuralı.</span></div>' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60"><strong class="text-cyan-400">🎵 TikTok FYP (Günde 4-5 Video):</strong> 09:30 • 12:45 • 16:15 • 19:15 • 22:00<br><span class="text-gray-400">300 kişilik batch testi. Min 2 saat ara (0-view jail koruması).</span></div>' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60"><strong class="text-red-400">▶️ YouTube Shorts (Günde 2 Video):</strong> 12:45 • 19:15<br><span class="text-gray-400">Shorts rafına 4-6 saatte girer. Günde max 2 paylaşım duplicate ve spam cezasını önler.</span></div>' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60"><strong class="text-blue-400">👥 Facebook Reels (Günde 3 Video):</strong> 09:30 • 12:45 • 19:15<br><span class="text-gray-400">Ebeveyn etkileşimi. Günde 3 paylaşım sayfa kalitesini korur.</span></div>' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60"><strong class="text-gray-300">🐦 X (Twitter) (Günde 5 Tweet):</strong> 09:30 • 12:45 • 16:15 • 19:15 • 22:00<br><span class="text-gray-400">Gündem ve akşam sohbet saatleri. Min 90 dk aralık.</span></div>' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60"><strong class="text-red-500">📌 Pinterest (Günde 3 Pin):</strong> 16:15 • 19:15 • 22:00<br><span class="text-gray-400">Görsel arama motoru. Akşam pin kaydetme zirve yapar.</span></div>' .
                                        '<div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60 col-span-full"><strong class="text-blue-500">💼 LinkedIn (Günde 1 Post):</strong> 09:30 (Sadece Hafta İçi İş Günleri)<br><span class="text-gray-400">Mesai başı iş akışı. Hafta sonu paylaşım yapılmaz, günde max 1 post kuralı esastır.</span></div>' .
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['playlist']))
            ->columns([
                TextColumn::make('name')
                    ->label('Kural Adı')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold'),

                TextColumn::make('playlist.name')
                    ->label('Video Kaynağı')
                    ->default('Tüm Video Havuzu (Sıralı)')
                    ->badge()
                    ->color('primary')
                    ->visibleFrom('sm')
                    ->sortable(),

                TextColumn::make('schedule_type')
                    ->label('Gün İçi Paylaşım Saatleri & Sıklık')
                    ->badge()
                    ->color(fn (Schedule $record): string => $record->schedule_type === 'algorithmic_growth' ? 'success' : 'info')
                    ->wrap()
                    ->formatStateUsing(function (Schedule $record): string {
                        $times = $record->getDailyRunTimes();
                        $count = count($times);
                        if ($record->schedule_type === 'algorithmic_growth') {
                            $strategyLabel = match ($record->growth_strategy) {
                                'conservative' => 'Hafif (2 Dalga)',
                                'balanced' => 'Dengeli (3 Dalga)',
                                default => 'Maksimum Güç (5 Dalga)',
                            };
                            return "🚀 Keşfet ({$strategyLabel}) • " . implode(' • ', $times);
                        }
                        if ($record->schedule_type === 'hourly') {
                            $start = $record->start_time ? substr($record->start_time, 0, 5) : '00:00';
                            $end = $record->end_time ? substr($record->end_time, 0, 5) : '23:00';
                            return "🕐 Her Saat Başı ({$start}-{$end}) [{$count} kez]";
                        }
                        if ($record->schedule_type === 'minute_interval') {
                            $start = $record->start_time ? substr($record->start_time, 0, 5) : '00:00';
                            $end = $record->end_time ? substr($record->end_time, 0, 5) : '23:59';
                            return "⚡ Her {$record->interval_minutes} Dk. Bir ({$start}-{$end}) [{$count} kez]";
                        }
                        if ($record->schedule_type === 'interval') {
                            return "⏳ Her {$record->interval_hours} Saatte Bir [{$count} kez]";
                        }
                        if ($record->schedule_type === 'custom_times') {
                            return implode(' • ', $times) . " ({$count} kez)";
                        }
                        return ($times[0] ?? '12:00') . ' (Günde 1 kez)';
                    }),

                TextColumn::make('last_run_at')
                    ->label('Son Çalışma')
                    ->dateTime('d.m.Y H:i')
                    ->visibleFrom('md')
                    ->sortable()
                    ->placeholder('Henüz çalışmadı'),

                ToggleColumn::make('is_active')
                    ->label('Aktif / Pasif'),
            ])
            ->actions([
                Tables\Actions\Action::make('run_now')
                    ->label('Sıradakini Şimdi Paylaş')
                    ->icon('heroicon-o-play')
                    ->iconButton()
                    ->tooltip('Sıradakini Şimdi Paylaş')
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
                    ->tooltip('Düzenle')
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::ThreeExtraLarge),
                Tables\Actions\DeleteAction::make()->iconButton()->tooltip('Sil'),
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
