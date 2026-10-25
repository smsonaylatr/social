<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\SocialAccount;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
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
                                'custom_times' => '⏰ Günde Birden Fazla Kez (Seçilen Saatlerde)',
                                'interval' => '⏳ Belirli Aralıklarla (Örn: Her 2 veya 3 Saatte Bir)',
                                'single' => '🎯 Günde 1 Kez (Belirli Tek Saatte)',
                            ])
                            ->default('custom_times')
                            ->live()
                            ->required(),

                        TimePicker::make('time_of_day')
                            ->label('Günlük Paylaşım Saati')
                            ->default('12:00:00')
                            ->seconds(false)
                            ->visible(fn (Get $get): bool => $get('schedule_type') === 'single')
                            ->required(fn (Get $get): bool => $get('schedule_type') === 'single'),

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

                        Grid::make(3)
                            ->schema([
                                TimePicker::make('start_time')
                                    ->label('İlk Paylaşım Saati')
                                    ->default('09:00:00')
                                    ->seconds(false)
                                    ->required(fn (Get $get): bool => $get('schedule_type') === 'interval'),

                                TimePicker::make('end_time')
                                    ->label('Son Paylaşım Saati')
                                    ->default('23:00:00')
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

                Section::make('Hedef Sosyal Medya Hesapları ve Limitler')
                    ->schema([
                        CheckboxList::make('target_social_account_ids')
                            ->label('Yayın Yapılacak Hesaplar')
                            ->options(SocialAccount::all()->mapWithKeys(fn ($a) => [$a->id => "{$a->platform->name} - {$a->name} ({$a->account_type})"]))
                            ->columns(2)
                            ->columnSpanFull(),

                        TextInput::make('max_posts_per_day')
                            ->label('Günlük Maksimum Paylaşım Limiti')
                            ->helperText('Bir gün içinde bu kural ile en fazla kaç video paylaşılsın (Örn: 10 adet).')
                            ->numeric()
                            ->default(10)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Otomasyonu Etkinleştir')
                            ->helperText('Etkinleştirildiğinde zamanı gelen paylaşımlar otomatik olarak kuyruğa aktarılır.')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Kural Adı')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('playlist.name')
                    ->label('Video Kaynağı')
                    ->default('Tüm Video Havuzu (Sıralı)')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('schedule_type')
                    ->label('Gün İçi Paylaşım Saatleri')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(function (Schedule $record): string {
                        $times = $record->getDailyRunTimes();
                        $count = count($times);
                        if ($record->schedule_type === 'interval') {
                            return "Her {$record->interval_hours} Saatte Bir (" . implode(', ', $times) . ") [{$count} kez]";
                        }
                        if ($record->schedule_type === 'custom_times') {
                            return implode(' • ', $times) . " ({$count} kez)";
                        }
                        return ($times[0] ?? '12:00') . ' (Günde 1 kez)';
                    }),

                TextColumn::make('max_posts_per_day')
                    ->label('Günlük Limit')
                    ->suffix(' adet')
                    ->sortable(),

                TextColumn::make('last_run_at')
                    ->label('Son Çalışma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('Henüz çalışmadı'),

                ToggleColumn::make('is_active')
                    ->label('Aktif / Pasif'),
            ])
            ->actions([
                Tables\Actions\Action::make('run_now')
                    ->label('Sıradakini Şimdi Paylaş')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->action(function (Schedule $record) {
                        $record->update(['is_active' => true]);
                        $post = app(\App\Services\Automation\AutomationOrchestratorService::class)->runSchedule($record);
                        if ($post) {
                            \Filament\Notifications\Notification::make()
                                ->title('Sıradaki Video Kuyruğa Alındı!')
                                ->body("Seçilen video: {$post->video?->title}")
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Paylaşım Yapılamadı')
                                ->body('Uygun video bulunamadı, hedef hesap seçilmedi veya günlük limit doldu.')
                                ->warning()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
