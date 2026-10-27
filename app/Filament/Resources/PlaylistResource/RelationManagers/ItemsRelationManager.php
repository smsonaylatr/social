<?php

namespace App\Filament\Resources\PlaylistResource\RelationManagers;

use App\Models\Video;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Listedeki Videolar';
    protected static ?string $modelLabel = 'Video';
    protected static ?string $pluralModelLabel = 'Listedeki Videolar';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('video_id')
                    ->label('Video')
                    ->options(Video::pluck('title', 'id'))
                    ->searchable()
                    ->required(),

                TextInput::make('sort_order')
                    ->label('Sıra No')
                    ->numeric()
                    ->default(1)
                    ->required(),

                TextInput::make('priority_score')
                    ->label('Öncelik Puanı (1-100)')
                    ->numeric()
                    ->default(10)
                    ->required(),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('video.title')
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->sortable(),

                ImageColumn::make('video.thumbnail_path')
                    ->label('Önizleme')
                    ->disk('public')
                    ->square()
                    ->defaultImageUrl(url('/images/video-placeholder.png'))
                    ->state(function ($record): ?string {
                        $video = $record->video;
                        if (! $video || empty($video->thumbnail_path)) {
                            return null;
                        }
                        if (! \Illuminate\Support\Facades\Storage::disk($video->disk ?? 'public')->exists($video->thumbnail_path)) {
                            return null;
                        }
                        return $video->thumbnail_path;
                    }),

                TextColumn::make('video.title')
                    ->label('Video Başlığı')
                    ->searchable()
                    ->wrap()
                    ->weight('bold'),

                TextColumn::make('video.aspect_ratio')
                    ->label('En-Boy')
                    ->badge()
                    ->visibleFrom('sm')
                    ->color('primary'),

                TextColumn::make('video.usage_count')
                    ->label('Yayın Sayısı')
                    ->visibleFrom('md')
                    ->badge(),

                TextColumn::make('priority_score')
                    ->label('Öncelik')
                    ->visibleFrom('lg'),

                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Listeye Video Ekle'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->iconButton()->tooltip('Düzenle'),
                Tables\Actions\DeleteAction::make()->iconButton()->tooltip('Sil'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
