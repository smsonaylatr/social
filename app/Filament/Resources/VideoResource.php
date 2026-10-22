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
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('thumbnail_path')
                    ->label('Önizleme')
                    ->disk('public')
                    ->square()
                    ->defaultImageUrl(url('/images/video-placeholder.png')),

                TextColumn::make('title')
                    ->label('Video Başlığı')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(45),

                TextColumn::make('duration_seconds')
                    ->label('Süre')
                    ->formatStateUsing(fn (Video $record): string => $record->formatted_duration)
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('aspect_ratio')
                    ->label('Format')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        '9:16' => 'success',
                        '16:9' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('file_size')
                    ->label('Dosya Boyutu')
                    ->formatStateUsing(fn (Video $record): string => $record->formatted_file_size)
                    ->sortable(),

                TextColumn::make('usage_count')
                    ->label('Yayın Sayısı')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->suffix(' kez')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (): string => 'Yayına Hazır'),

                TextColumn::make('created_at')
                    ->label('Yüklenme Tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
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
                    ->color('primary')
                    ->modalHeading(fn (Video $record): string => $record->title)
                    ->modalContent(fn (Video $record) => view('filament.modals.video-player-modal', ['video' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat'),

                Tables\Actions\DeleteAction::make()
                    ->label('Sil'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('mark_as_ready')
                        ->label('Yayına Hazır Olarak İşaretle')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Collection $records) => $records->each->update(['status' => VideoStatus::READY])),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
