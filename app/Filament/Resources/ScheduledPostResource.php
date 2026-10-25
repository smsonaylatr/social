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
use Filament\Support\Enums\MaxWidth;
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
            ->modifyQueryUsing(fn ($query) => $query->with(['video', 'playlist', 'targets']))
            ->emptyStateHeading('Henüz Planlanmış veya Sıralı Kuyruk Yok')
            ->emptyStateDescription('Sağ üstteki yeşil "Otomatik Sırayla Kuyruk Oluştur" butonuna tıklayarak Video Havuzundaki tüm videolarınızı seçtiğiniz saat aralıklarıyla otomatik olarak sıraya dizebilirsiniz.')
            ->defaultSort('scheduled_at', 'desc')
            ->columns([
                ImageColumn::make('video.thumbnail_path')
                    ->label('Önizleme')
                    ->disk('public')
                    ->width(70)
                    ->height(105)
                    ->extraImgAttributes([
                        'class' => 'rounded-xl object-cover shadow-md border border-slate-700/60 transition-transform duration-200 hover:scale-105 cursor-pointer shrink-0',
                        'style' => 'width: 70px !important; height: 105px !important; min-width: 70px; min-height: 105px; object-fit: cover; border-radius: 12px;',
                    ])
                    ->defaultImageUrl(url('/images/video-placeholder.png'))
                    ->action(
                        Action::make('preview_video_modal')
                            ->modalHeading(fn (ScheduledPost $record) => $record->video?->title ?? 'Video Önizleme')
                            ->modalWidth(\Filament\Support\Enums\MaxWidth::Small)
                            ->modalContent(fn (ScheduledPost $record) => view('filament.modals.video-player-modal', ['video' => $record->video]))
                    ),

                TextColumn::make('video.title')
                    ->label('Video')
                    ->searchable()
                    ->wrap()
                    ->weight('bold')
                    ->description(fn (ScheduledPost $record): string => ($record->playlist?->name ? $record->playlist->name . ' • ' : '') . $record->scheduled_at->diffForHumans()),

                TextColumn::make('scheduled_at')
                    ->label('Planlanan Zaman')
                    ->dateTime('d.m.Y H:i')
                    ->description(function (ScheduledPost $record): ?string {
                        if (! $record->scheduled_at) {
                            return null;
                        }
                        $hour = (int) $record->scheduled_at->format('H');
                        return match (true) {
                            $hour >= 8 && $hour <= 10 => '🌅 Dalga 1: Sabah Zirvesi',
                            $hour >= 12 && $hour <= 14 => '☀️ Dalga 2: Öğle Molası',
                            $hour >= 15 && $hour <= 17 => '☕ Dalga 3: İkindi / Çıkış',
                            $hour >= 18 && $hour <= 20 => '🔥 Dalga 4: Prime Keşfet',
                            $hour >= 21 && $hour <= 23 => '🌙 Dalga 5: Gece Rahatlama',
                            default => '⏱️ Özel Saat',
                        };
                    })
                    ->visibleFrom('md')
                    ->sortable(),

                TextColumn::make('targets.platform_code')
                    ->label('Platformlar')
                    ->badge()
                    ->visibleFrom('sm')
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
                    ->label('Durum')
                    ->badge()
                    ->color(fn (PublishStatus $state): string => $state->color())
                    ->formatStateUsing(fn (PublishStatus $state): string => $state->label()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Yayın Durumu')
                    ->options(collect(PublishStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                Action::make('publish_now')
                    ->label('Şimdi Gönder')
                    ->icon('heroicon-o-paper-airplane')
                    ->iconButton()
                    ->tooltip('Şimdi Gönder')
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

                        try {
                            \Illuminate\Support\Facades\Artisan::call('queue:work', [
                                '--stop-when-empty' => true,
                                '--max-jobs' => 20,
                                '--tries' => 3,
                            ]);
                        } catch (\Throwable) {}

                        $record->refresh();
                        $publishedCount = $record->targets()->where('status', PublishStatus::PUBLISHED)->count();
                        $failedTargets = $record->targets()->where('status', PublishStatus::FAILED)->get();

                        if ($publishedCount > 0) {
                            Notification::make()
                                ->title('Gönderi Paylaşıldı! 🚀')
                                ->body("{$publishedCount} kanala başarıyla yüklendi.")
                                ->success()
                                ->duration(8000)
                                ->send();
                        } elseif ($failedTargets->isNotEmpty()) {
                            $err = $failedTargets->first()->error_message ?: 'Platform API hatası';
                            Notification::make()
                                ->title('Paylaşım Sırasında Hata Oluştu')
                                ->body("Hata: {$err}")
                                ->danger()
                                ->duration(10000)
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Yayın görevi yürütülüyor')
                                ->body('Hedefler kuyruğa alındı ve işleniyor.')
                                ->info()
                                ->send();
                        }
                    }),

                Action::make('view_details')
                    ->label('Detaylar')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip('Detayları Gör')
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
