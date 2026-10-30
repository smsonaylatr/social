<?php

namespace App\Filament\Resources;

use App\Enums\VideoStatus;
use App\Filament\Resources\VideoResource\Pages;
use App\Models\Video;
use App\Services\Media\VideoProcessingService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class VideoResource extends Resource
{
    protected static ?string $model = Video::class;

    protected static ?string $navigationIcon = 'heroicon-o-film';
    protected static ?string $navigationGroup = 'Video Yönetimi';
    protected static ?string $navigationLabel = 'Video Havuzu';
    protected static ?string $modelLabel = 'Video';
    protected static ?string $pluralModelLabel = 'Video Havuzu';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Toplu Video Yükleme Alanı')
                    ->description('Tüm video dosyalarınızı (MP4, MOV, WebM) tek seferde buraya sürükleyip bırakabilirsiniz. Başlık, süre, çözünürlük, en-boy oranı ve kapak görseli otomatik olarak oluşturulacaktır.')
                    ->schema([
                        FileUpload::make('video_files')
                            ->label('Videolarınızı Sürükleyip Bırakın ya da Dosya Seçin')
                            ->multiple()
                            ->appendFiles()
                            ->preserveFilenames()
                            ->moveFiles()
                            ->maxParallelUploads(2)
                            ->disk('public')
                            ->directory('videos')
                            ->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/webm', 'video/x-msvideo'])
                            ->maxSize(512000) // 500 MB
                            ->panelLayout('grid')
                            ->reorderable()
                            ->openable()
                            ->downloadable()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->visible(fn (string $context): bool => $context === 'create')
                            ->columnSpanFull(),

                        TextInput::make('title')
                            ->label('Video Başlığı')
                            ->visible(fn (string $context): bool => $context === 'edit')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $isGrid = $livewire && method_exists($livewire, 'isGridLayout') && $livewire->isGridLayout();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['brand', 'category']))
            ->defaultSort('id', 'asc')
            ->recordAction('play')
            ->recordUrl(null)
            ->columns(
                $isGrid
                    ? static::getGridTableColumns()
                    : static::getListTableColumns()
            )
            ->contentGrid(
                fn () => ($table->getLivewire() && method_exists($table->getLivewire(), 'isGridLayout') && $table->getLivewire()->isGridLayout())
                    ? [
                        'sm' => 2,
                        'md' => 3,
                        'lg' => 4,
                        'xl' => 4,
                        '2xl' => 5,
                    ]
                    : null
            )
            ->paginationPageOptions([12, 24, 36, 48, 96])
            ->defaultPaginationPageOption(24)
            ->filters([
                SelectFilter::make('aspect_ratio')
                    ->label('Format (En-Boy)')
                    ->options([
                        '9:16' => '9:16 (Dikey / Reels & Shorts)',
                        '16:9' => '16:9 (Yatay / YouTube)',
                        '1:1' => '1:1 (Kare / Akış)',
                    ]),
            ])
            ->actions([
                Action::make('play')
                    ->label('İzle')
                    ->icon('heroicon-o-play')
                    ->iconButton()
                    ->tooltip('Videoyu İzle')
                    ->color('primary')
                    ->modalWidth(MaxWidth::Small)
                    ->modalHeading(fn (Video $record): string => $record->title)
                    ->modalDescription(fn (Video $record): string => "{$record->aspect_ratio} • {$record->formatted_duration} • {$record->formatted_file_size}")
                    ->modalContent(fn (Video $record) => view('filament.modals.video-player-modal', ['video' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalFooterActions([]),

                Action::make('edit_title')
                    ->label('Başlığı Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->tooltip('Başlığı Düzenle')
                    ->form([
                        TextInput::make('title')
                            ->label('Video Başlığı')
                            ->required(),
                    ])
                    ->fillForm(fn (Video $record): array => [
                        'title' => $record->title,
                    ])
                    ->action(function (Video $record, array $data): void {
                        $record->update(['title' => $data['title']]);
                        Notification::make()
                            ->title('Video başlığı güncellendi.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->iconButton()
                    ->tooltip('Videoyu Sil'),
            ])
            ->headerActions([
                Action::make('reanalyze_all')
                    ->label('Yeniden Analiz Et')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Tüm Videoları Yeniden Analiz Et')
                    ->modalDescription('Kayıtlı tüm videoların süre, çözünürlük, en-boy oranı ve önizleme görselleri baştan taranacaktır.')
                    ->action(function () {
                        $service = app(VideoProcessingService::class);
                        $videos = Video::all();
                        $count = 0;
                        foreach ($videos as $video) {
                            try {
                                if ($video->storage_path && Storage::disk($video->disk ?? 'public')->exists($video->storage_path)) {
                                    $metadata = $service->analyzeVideo($video->storage_path);
                                    $video->update($metadata->toArray());
                                    $count++;
                                }
                            } catch (\Throwable $e) {
                                // continue
                            }
                        }

                        // Rename sequential if titles are hashes or numeric IDs
                        $orderedVideos = Video::orderBy('id', 'asc')->get();
                        $seq = 1;
                        foreach ($orderedVideos as $v) {
                            $title = trim($v->title);
                            $isHashOrId = ctype_digit($title)
                                || (strlen($title) >= 15 && ! str_contains($title, ' '))
                                || preg_match('/^(vid|img|pxl|mov|rec|screen|wp|tiktok)[-_0-9]/i', $title)
                                || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}/i', $title);

                            if ($isHashOrId) {
                                $v->update(['title' => "{$seq}. Video"]);
                            }
                            $seq++;
                        }
                        Notification::make()
                            ->title("{$count} video başarıyla yeniden analiz edildi.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('reanalyze_selected')
                        ->label('Seçilenleri Yeniden Analiz Et & Onar')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function (Collection $records) {
                            $service = app(VideoProcessingService::class);
                            $count = 0;
                            foreach ($records as $video) {
                                try {
                                    if ($video->storage_path && Storage::disk($video->disk ?? 'public')->exists($video->storage_path)) {
                                        $metadata = $service->analyzeVideo($video->storage_path);
                                        $video->update($metadata->toArray());
                                        $count++;
                                    }
                                } catch (\Throwable $e) {
                                    // continue
                                }
                            }
                            Notification::make()
                                ->title("{$count} video başarıyla yeniden analiz edildi.")
                                ->success()
                                ->send();
                        }),

                    BulkAction::make('mark_as_ready')
                        ->label('Yayına Hazır Olarak İşaretle')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Collection $records) => $records->each->update(['status' => VideoStatus::READY])),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getListTableColumns(): array
    {
        return [
            ViewColumn::make('preview')
                ->label('Önizleme')
                ->view('filament.tables.columns.video-preview')
                ->grow(false)
                ->action(
                    Action::make('preview_play')
                        ->label('İzle')
                        ->modalWidth(MaxWidth::Small)
                        ->modalHeading(fn (Video $record): string => $record->title)
                        ->modalDescription(fn (Video $record): string => "{$record->aspect_ratio} • {$record->formatted_duration} • {$record->formatted_file_size}")
                        ->modalContent(fn (Video $record) => view('filament.modals.video-player-modal', ['video' => $record]))
                        ->modalSubmitAction(false)
                        ->modalCancelAction(false)
                        ->modalFooterActions([])
                ),

            TextColumn::make('title')
                ->label('Video Başlığı')
                ->weight('bold')
                ->searchable()
                ->sortable(query: function (Builder $query, string $direction): Builder {
                    $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
                    $driver = $query->getConnection()->getDriverName();

                    if ($driver === 'mysql' || $driver === 'mariadb') {
                        return $query
                            ->orderByRaw("CASE WHEN title REGEXP '^[0-9]' THEN 0 WHEN title REGEXP '[0-9]' THEN 1 ELSE 2 END ASC")
                            ->orderByRaw("CAST(REGEXP_SUBSTR(title, '[0-9]+') AS UNSIGNED) {$dir}")
                            ->orderBy('title', $dir);
                    }

                    return $query
                        ->orderByRaw("CAST(title AS UNSIGNED) {$dir}")
                        ->orderBy('title', $dir);
                })
                ->wrap()
                ->description(function (Video $record): ?string {
                    $parts = [];
                    if ($record->aspect_ratio) {
                        $parts[] = $record->aspect_ratio;
                    }
                    if ($record->formatted_duration) {
                        $parts[] = $record->formatted_duration;
                    }
                    if ($record->formatted_file_size) {
                        $parts[] = $record->formatted_file_size;
                    }
                    if ($record->usage_count > 0) {
                        $parts[] = "{$record->usage_count} kez yayınlandı";
                    }

                    return count($parts) > 0 ? implode(' • ', $parts) : null;
                }),

            TextColumn::make('duration_seconds')
                ->label('Süre')
                ->formatStateUsing(fn (Video $record): string => $record->formatted_duration)
                ->badge()
                ->color('info')
                ->sortable()
                ->visibleFrom('sm')
                ->toggleable(),

            TextColumn::make('aspect_ratio')
                ->label('Format')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    '9:16' => 'success',
                    '16:9' => 'primary',
                    default => 'gray',
                })
                ->sortable()
                ->visibleFrom('md')
                ->toggleable(),

            TextColumn::make('file_size')
                ->label('Dosya Boyutu')
                ->formatStateUsing(fn (Video $record): string => $record->formatted_file_size)
                ->sortable()
                ->visibleFrom('lg')
                ->toggleable(),

            TextColumn::make('usage_count')
                ->label('Yayın Sayısı')
                ->badge()
                ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                ->suffix(' kez')
                ->sortable()
                ->visibleFrom('lg')
                ->toggleable(),

            TextColumn::make('status')
                ->label('Durum')
                ->badge()
                ->color('success')
                ->formatStateUsing(fn (): string => 'Yayına Hazır')
                ->visibleFrom('xl')
                ->toggleable(),

            TextColumn::make('created_at')
                ->label('Yüklenme Tarihi')
                ->dateTime('d.m.Y H:i')
                ->sortable()
                ->visibleFrom('xl')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function getGridTableColumns(): array
    {
        return [
            Tables\Columns\Layout\Stack::make([
                Tables\Columns\Layout\View::make('filament.tables.columns.video-grid-card'),
                TextColumn::make('title')
                    ->searchable()
                    ->hidden(),
            ])->space(0),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVideos::route('/'),
            'create' => Pages\CreateVideo::route('/create'),
            'view' => Pages\ViewVideo::route('/{record}'),
            'edit' => Pages\EditVideo::route('/{record}/edit'),
        ];
    }
}
