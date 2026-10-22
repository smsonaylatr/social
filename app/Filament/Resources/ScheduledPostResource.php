<?php

namespace App\Filament\Resources;

use App\Enums\PublishStatus;
use App\Filament\Resources\ScheduledPostResource\Pages;
use App\Jobs\DispatchPlatformPostJob;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\Publisher\SchedulingService;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScheduledPostResource extends Resource
{
    protected static ?string $model = ScheduledPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Yayın ve Otomasyon';
    protected static ?string $navigationLabel = 'Paylaşım Takvimi & Kuyruk';
    protected static ?string $modelLabel = 'Planlanmış Gönderi';
    protected static ?string $pluralModelLabel = 'Paylaşım Takvimi';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('video_id')
                    ->label('Yayınlanacak Video')
                    ->relationship('video', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),

                DateTimePicker::make('scheduled_at')
                    ->label('Planlanan Yayın Tarihi ve Saati')
                    ->default(now()->addMinutes(15))
                    ->required(),

                Select::make('social_account_ids')
                    ->label('Hedef Sosyal Medya Hesapları')
                    ->multiple()
                    ->options(SocialAccount::all()->mapWithKeys(fn ($a) => [$a->id => "{$a->platform->name} - {$a->name}"]))
                    ->required(),

                Textarea::make('notes')
                    ->label('Yayın Notları')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('scheduled_at', 'desc')
            ->columns([
                ImageColumn::make('video.thumbnail_path')
                    ->label('Thumbnail')
                    ->disk('public')
                    ->square()
                    ->defaultImageUrl(url('/images/video-placeholder.png')),

                TextColumn::make('video.title')
                    ->label('Video')
                    ->searchable()
                    ->limit(35)
                    ->description(fn (ScheduledPost $record): string => $record->playlist?->name ?? 'Tek Seferlik Planlama'),

                TextColumn::make('scheduled_at')
                    ->label('Planlanan Zaman')
                    ->dateTime('d.m.Y H:i')
                    ->description(fn (ScheduledPost $record): string => $record->scheduled_at->diffForHumans())
                    ->sortable(),

                TextColumn::make('targets.platform_code')
                    ->label('Platformlar & Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'instagram' => 'warning',
                        'youtube' => 'danger',
                        'tiktok' => 'primary',
                        'facebook' => 'info',
                        'linkedin' => 'primary',
                        'x' => 'gray',
                        default => 'secondary',
                    }),

                TextColumn::make('status')
                    ->label('Genel Durum')
                    ->badge()
                    ->color(fn (PublishStatus $state): string => $state->color())
                    ->formatStateUsing(fn (PublishStatus $state): string => $state->label()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PublishStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ])
            ->actions([
                Action::make('publish_now')
                    ->label('Şimdi Gönder')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Gönderiyi Anında Kuyruğa Al')
                    ->modalDescription('Bu video seçili tüm sosyal medya hesaplarına derhal gönderilecektir. Devam etmek istiyor musunuz?')
                    ->action(function (ScheduledPost $record) {
                        $record->update(['status' => PublishStatus::QUEUED]);

                        foreach ($record->targets as $target) {
                            $target->update(['status' => PublishStatus::QUEUED]);
                            DispatchPlatformPostJob::dispatch($target->id);
                        }

                        Notification::make()
                            ->title('Yayın görevi başlatıldı')
                            ->body('Tüm platform hedefleri kuyruğa alındı ve işleniyor.')
                            ->success()
                            ->send();
                    }),

                Action::make('view_details')
                    ->label('Detaylar')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Yayın Hedefleri ve Detayları')
                    ->modalContent(fn (ScheduledPost $record) => view('filament.modals.scheduled-post-details', ['post' => $record])),

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
            'index' => Pages\ListScheduledPosts::route('/'),
            'create' => Pages\CreateScheduledPost::route('/create'),
        ];
    }
}
