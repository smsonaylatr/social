<?php

namespace App\Filament\Resources;

use App\Enums\PlaylistLoopMode;
use App\Filament\Resources\PlaylistResource\Pages;
use App\Filament\Resources\PlaylistResource\RelationManagers\ItemsRelationManager;
use App\Models\Playlist;
use App\Services\Playlist\CircularPlaylistEngine;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PlaylistResource extends Resource
{
    protected static ?string $model = Playlist::class;
    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'Video Yönetimi';
    protected static ?string $navigationLabel = 'Sıralı Oynatma Listeleri';
    protected static ?string $modelLabel = 'Oynatma Listesi';
    protected static ?string $pluralModelLabel = 'Oynatma Listeleri';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Liste Bilgileri')
                    ->schema([
                        TextInput::make('name')
                            ->label('Liste Adı')
                            ->placeholder('Örn: Patenli Ayakkabılar Günlük Video Paylaşımı')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255),

                        Select::make('brand_id')
                            ->label('Marka')
                            ->relationship('brand', 'name')
                            ->default(1)
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('loop_mode')
                            ->label('Döngü Davranışı')
                            ->options([
                                PlaylistLoopMode::SEQUENTIAL->value => 'Sıralı Yayınla (Sıra 1 -> 2 -> 3)',
                                PlaylistLoopMode::SHUFFLE->value => 'Karışık Sırayla Yayınla',
                                PlaylistLoopMode::PRIORITY->value => 'Öncelik Puanına Göre Yayınla',
                            ])
                            ->default(PlaylistLoopMode::SEQUENTIAL->value)
                            ->required(),

                        Textarea::make('description')
                            ->label('Açıklama')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Döngü ve Yinelenen İçerik Kuralları')
                    ->description('Platform politikalarına uyum için tekrar yayın kurallarını belirleyin')
                    ->schema([
                        Toggle::make('is_circular')
                            ->label('Döngüsel Yayın (Havuz bitince başa dön)')
                            ->helperText('Liste sonuna gelindiğinde uygunluk kriterlerine göre baştan devam eder.')
                            ->default(true),

                        TextInput::make('cooldown_days')
                            ->label('Minimum Tekrar Aralığı (Gün)')
                            ->helperText('Aynı videonun tekrar yayınlanabilmesi için geçmesi gereken gün sayısı.')
                            ->numeric()
                            ->default(14)
                            ->required(),

                        Toggle::make('pause_when_exhausted')
                            ->label('Tüm İçerik Bitince Otomasyonu Duraklat')
                            ->helperText('Yeniden kullanılabilir içerik kalmadığında beklemeye al.')
                            ->default(false),

                        Toggle::make('prioritize_new_videos')
                            ->label('Yeni Eklenen Videoları Öncelikli Yap')
                            ->helperText('Daha önce hiç yayınlanmamış videoları sıranın önüne alır.')
                            ->default(true),

                        Select::make('status')
                            ->label('Liste Durumu')
                            ->options([
                                'active' => 'Aktif',
                                'paused' => 'Duraklatıldı',
                                'waiting_content' => 'Uygun İçerik Bekleniyor',
                            ])
                            ->default('active')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Liste Adı')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Playlist $record): string => $record->brand?->name ?? 'Varsayılan Marka'),

                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Video Sayısı')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('loop_mode')
                    ->label('Döngü Modu')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (PlaylistLoopMode $state): string => $state->label()),

                TextColumn::make('cursor.current_index')
                    ->label('İmleç Sırası')
                    ->formatStateUsing(function (Playlist $record) {
                        $idx = $record->cursor?->current_index ?? 0;
                        $cycles = $record->cursor?->cycle_count ?? 0;
                        return "Sıradaki: #{$idx} ({$cycles}. Döngü)";
                    }),

                TextColumn::make('cursor.currentVideo.title')
                    ->label('Son Yayınlanan')
                    ->limit(25)
                    ->default('Henüz Yayınlanmadı'),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'paused' => 'gray',
                        'waiting_content' => 'warning',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'paused' => 'Duraklatıldı',
                        'waiting_content' => 'Uygun İçerik Bekleniyor',
                        default => $state,
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'paused' => 'Duraklatıldı',
                        'waiting_content' => 'İçerik Bekleniyor',
                    ]),
            ])
            ->actions([
                Action::make('test_next_video')
                    ->label('Sıradaki Videoyu Seç')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->action(function (Playlist $record) {
                        $engine = app(CircularPlaylistEngine::class);
                        $nextVideo = $engine->getNextVideo($record);

                        if ($nextVideo) {
                            $cursor = $record->cursor;
                            Notification::make()
                                ->title('Sıradaki Video Seçildi')
                                ->body("Seçilen Video: {$nextVideo->title}\nİmleç: #{$cursor->current_index} (Döngü: {$cursor->cycle_count})")
                                ->success()
                                ->persistent()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Uygun Video Bulunamadı')
                                ->body('Tüm videolar bekleme süresinde veya kısıtlı. Yeni video eklenmesi bekleniyor.')
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

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlaylists::route('/'),
            'create' => Pages\CreatePlaylist::route('/create'),
            'edit' => Pages\EditPlaylist::route('/{record}/edit'),
        ];
    }
}
