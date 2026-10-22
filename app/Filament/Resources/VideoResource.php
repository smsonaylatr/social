<?php

namespace App\Filament\Resources;

use App\Enums\VideoStatus;
use App\Filament\Resources\VideoResource\Pages;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Services\Media\VideoProcessingService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\BadgeColumn;
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
                Section::make('Video Dosyası ve Önizleme')
                    ->description('MP4, QuickTime (MOV) veya WebM formatında video yükleyin')
                    ->schema([
                        FileUpload::make('storage_path')
                            ->label('Video Dosyası')
                            ->disk('public')
                            ->directory('videos')
                            ->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/webm', 'video/x-msvideo'])
                            ->maxSize(512000) // 500 MB
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('title')
                            ->label('Video Başlığı')
                            ->placeholder('Örn: Patenli Ayakkabı Yeni Sezon Tanıtımı')
                            ->required()
                            ->maxLength(255),

                        Select::make('category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('brand_id')
                            ->label('Marka')
                            ->relationship('brand', 'name')
                            ->default(1)
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('status')
                            ->label('Yayın Durumu')
                            ->options([
                                VideoStatus::READY->value => 'Yayına Hazır',
                                VideoStatus::PENDING->value => 'Beklemede',
                                VideoStatus::ARCHIVED->value => 'Arşivlendi',
                            ])
                            ->default(VideoStatus::READY->value)
                            ->required(),
                    ])->columns(2),

                Section::make('İçerik, SEO ve Özel Tanımlamalar')
                    ->schema([
                        Textarea::make('custom_caption')
                            ->label('Özel Video Açıklaması')
                            ->placeholder('Yapay zekâ yerine videoya özel bir açıklama kullanmak isterseniz buraya yazın...')
                            ->rows(3)
                            ->columnSpanFull(),

                        TagsInput::make('custom_hashtags')
                            ->label('Özel Hashtag\'ler')
                            ->placeholder('Hashtag ekle ve enter\'a bas')
                            ->columnSpanFull(),

                        TextInput::make('product_url')
                            ->label('İlgili Ürün / Sayfa Bağlantısı')
                            ->placeholder('https://patenliayakkabilar.com/urun-sayfasi')
                            ->url()
                            ->columnSpanFull(),

                        CheckboxList::make('restricted_platforms')
                            ->label('Yayınlanması Engellenecek Platformlar')
                            ->options([
                                'facebook' => 'Facebook',
                                'instagram' => 'Instagram',
                                'youtube' => 'YouTube',
                                'x' => 'X (Twitter)',
                                'pinterest' => 'Pinterest',
                                'tiktok' => 'TikTok',
                                'linkedin' => 'LinkedIn',
                            ])
                            ->columns(4)
                            ->columnSpanFull(),

                        Toggle::make('is_reusable')
                            ->label('Döngüde Yeniden Kullanıma İzin Ver')
                            ->helperText('Döngü sona erdiğinde bekleme süresi sonrasında bu video tekrar sıraya girebilir mi?')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->description(fn (Video $record): string => $record->category?->name ?? 'Kategorisiz')
                    ->limit(35),

                TextColumn::make('duration_seconds')
                    ->label('Süre')
                    ->formatStateUsing(fn (Video $record): string => $record->formatted_duration)
                    ->sortable(),

                TextColumn::make('aspect_ratio')
                    ->label('En-Boy')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('file_size')
                    ->label('Boyut')
                    ->formatStateUsing(fn (Video $record): string => $record->formatted_file_size)
                    ->sortable(),

                TextColumn::make('usage_count')
                    ->label('Yayın Sayısı')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (VideoStatus $state): string => match ($state) {
                        VideoStatus::READY => 'success',
                        VideoStatus::PROCESSING, VideoStatus::PENDING => 'warning',
                        VideoStatus::FAILED => 'danger',
                        VideoStatus::ARCHIVED => 'gray',
                    })
                    ->formatStateUsing(fn (VideoStatus $state): string => $state->label()),

                TextColumn::make('created_at')
                    ->label('Yüklenme')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),

                SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        VideoStatus::READY->value => 'Yayına Hazır',
                        VideoStatus::PENDING->value => 'Beklemede',
                        VideoStatus::ARCHIVED->value => 'Arşivlendi',
                    ]),

                SelectFilter::make('aspect_ratio')
                    ->label('En-Boy Oranı')
                    ->options([
                        '9:16' => '9:16 (Dikey / Reels / Shorts)',
                        '16:9' => '16:9 (Yatay / Klasik)',
                        '1:1' => '1:1 (Kare / Feed)',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Action::make('reanalyze')
                    ->label('Yeniden Analiz Et')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Video $record) {
                        try {
                            $service = app(VideoProcessingService::class);
                            $metadata = $service->analyzeVideo($record->storage_path);
                            $record->update($metadata->toArray());
                            Notification::make()
                                ->title('Metadata Analizi Tamamlandı')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Analiz Hatası')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('mark_as_ready')
                        ->label('Yayına Hazır Yap')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Collection $records) => $records->each->update(['status' => VideoStatus::READY])),

                    BulkAction::make('archive')
                        ->label('Arşivle')
                        ->icon('heroicon-o-archive-box')
                        ->action(fn (Collection $records) => $records->each->update(['status' => VideoStatus::ARCHIVED])),

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
