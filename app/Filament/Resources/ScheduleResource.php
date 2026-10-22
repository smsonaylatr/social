<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\SocialAccount;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                            ->placeholder('Örn: Patenli Ayakkabılar Günlük 12:00 Yayını')
                            ->required()
                            ->maxLength(255),

                        Select::make('playlist_id')
                            ->label('Video Oynatma Listesi (Havuz)')
                            ->options(Playlist::pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        TimePicker::make('time_of_day')
                            ->label('Günlük Paylaşım Saati')
                            ->default('12:00:00')
                            ->seconds(false)
                            ->required(),

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
                            ->numeric()
                            ->default(3)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Otomasyonu Etkinleştir')
                            ->helperText('Etkinleştirildiğinde zamanı gelen paylaşımlar otomatik olarak kuyruğa aktarılır.')
                            ->default(false),
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
                    ->label('Oynatma Listesi')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('time_of_day')
                    ->label('Yayın Saati')
                    ->time('H:i')
                    ->sortable(),

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
