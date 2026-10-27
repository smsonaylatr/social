<?php

namespace App\Filament\Widgets;

use App\Enums\PublishStatus;
use App\Models\ScheduledPost;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingScheduledPostsWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Yaklaşan Paylaşım Takvimi';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ScheduledPost::query()
                    ->with(['video', 'targets.socialAccount', 'brand'])
                    ->whereIn('status', [PublishStatus::SCHEDULED, PublishStatus::PENDING_APPROVAL, PublishStatus::QUEUED])
                    ->where('scheduled_at', '>=', now()->subHours(1))
                    ->orderBy('scheduled_at', 'asc')
                    ->limit(5)
            )
            ->columns([
                ImageColumn::make('video.thumbnail_path')
                    ->label('Thumbnail')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(url('/images/video-placeholder.png'))
                    ->state(function (ScheduledPost $record): ?string {
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
                    ->description(fn (ScheduledPost $record): string => $record->brand?->name ?? 'Varsayılan Marka')
                    ->tooltip(fn (ScheduledPost $record): ?string => $record->caption)
                    ->searchable()
                    ->limit(35),

                TextColumn::make('scheduled_at')
                    ->label('Planlanan Zaman')
                    ->dateTime('d.m.Y H:i')
                    ->description(fn (ScheduledPost $record): string => $record->scheduled_at->diffForHumans())
                    ->sortable(),

                TextColumn::make('targets_count')
                    ->counts('targets')
                    ->label('Hedef Hesaplar')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (PublishStatus $state): string => $state->color())
                    ->formatStateUsing(fn (PublishStatus $state): string => $state->label()),
            ])
            ->emptyStateHeading('Yaklaşan planlı paylaşım yok')
            ->emptyStateDescription('Yeni bir video yükleyip takvime ekleyebilir veya otomasyon döngüsünü başlatabilirsiniz.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }
}
