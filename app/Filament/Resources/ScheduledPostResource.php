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
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            ->defaultSort('scheduled_at', 'asc')
            ->columns([
                ViewColumn::make('preview')
                    ->label('Önizleme')
                    ->view('filament.tables.columns.scheduled-post-preview')
                    ->grow(false)
                    ->action(
                        Action::make('preview_video_modal')
                            ->modalHeading(fn (ScheduledPost $record) => $record->video?->title ?? 'Video Önizleme')
                            ->modalWidth(MaxWidth::Small)
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
            ->headerActions([
                ActionGroup::make([
                    Action::make('clear_platform')
                        ->label('🌐 Tüm Platformlar (Filtreyi Temizle)')
                        ->action(function ($livewire) {
                            $filters = $livewire->tableFilters ?? [];
                            $filters['platform']['values'] = [];
                            $filters['platform']['value'] = null;
                            $livewire->tableFilters = $filters;
                            $livewire->getTableFiltersForm()->fill($livewire->tableFilters);
                            $livewire->resetPage();
                        }),
                    Action::make('filter_instagram')
                        ->label('📸 Instagram')
                        ->action(function ($livewire) {
                            $filters = $livewire->tableFilters ?? [];
                            $filters['platform']['values'] = ['instagram'];
                            $filters['platform']['value'] = null;
                            $livewire->tableFilters = $filters;
                            $livewire->getTableFiltersForm()->fill($livewire->tableFilters);
                            $livewire->resetPage();
                        }),
                    Action::make('filter_youtube')
                        ->label('▶️ YouTube')
                        ->action(function ($livewire) {
                            $filters = $livewire->tableFilters ?? [];
                            $filters['platform']['values'] = ['youtube'];
                            $filters['platform']['value'] = null;
                            $livewire->tableFilters = $filters;
                            $livewire->getTableFiltersForm()->fill($livewire->tableFilters);
                            $livewire->resetPage();
                        }),
                    Action::make('filter_tiktok')
                        ->label('🎵 TikTok')
                        ->action(function ($livewire) {
                            $filters = $livewire->tableFilters ?? [];
                            $filters['platform']['values'] = ['tiktok'];
                            $filters['platform']['value'] = null;
                            $livewire->tableFilters = $filters;
                            $livewire->getTableFiltersForm()->fill($livewire->tableFilters);
                            $livewire->resetPage();
                        }),
                    Action::make('filter_facebook')
                        ->label('👥 Facebook')
                        ->action(function ($livewire) {
                            $filters = $livewire->tableFilters ?? [];
                            $filters['platform']['values'] = ['facebook'];
                            $filters['platform']['value'] = null;
                            $livewire->tableFilters = $filters;
                            $livewire->getTableFiltersForm()->fill($livewire->tableFilters);
                            $livewire->resetPage();
                        }),
                    Action::make('filter_linkedin')
                        ->label('💼 LinkedIn')
                        ->action(function ($livewire) {
                            $filters = $livewire->tableFilters ?? [];
                            $filters['platform']['values'] = ['linkedin'];
                            $filters['platform']['value'] = null;
                            $livewire->tableFilters = $filters;
                            $livewire->getTableFiltersForm()->fill($livewire->tableFilters);
                            $livewire->resetPage();
                        }),
                    Action::make('filter_x')
                        ->label('𝕏 X (Twitter)')
                        ->action(function ($livewire) {
                            $filters = $livewire->tableFilters ?? [];
                            $filters['platform']['values'] = ['x'];
                            $filters['platform']['value'] = null;
                            $livewire->tableFilters = $filters;
                            $livewire->getTableFiltersForm()->fill($livewire->tableFilters);
                            $livewire->resetPage();
                        }),
                    Action::make('filter_pinterest')
                        ->label('📌 Pinterest')
                        ->action(function ($livewire) {
                            $filters = $livewire->tableFilters ?? [];
                            $filters['platform']['values'] = ['pinterest'];
                            $filters['platform']['value'] = null;
                            $livewire->tableFilters = $filters;
                            $livewire->getTableFiltersForm()->fill($livewire->tableFilters);
                            $livewire->resetPage();
                        }),
                    Action::make('multi_platform_modal')
                        ->label('⚙️ Çoklu Platform Filtrele...')
                        ->modalHeading('Platformlara Göre Filtrele')
                        ->modalDescription('Paylaşım takviminde listelemek istediğiniz platformları seçin.')
                        ->modalSubmitActionLabel('Filtreyi Uygula')
                        ->modalWidth(MaxWidth::Small)
                        ->form([
                            CheckboxList::make('selected_platforms')
                                ->label('Platformlar')
                                ->options([
                                    'instagram' => '📸 Instagram',
                                    'youtube' => '▶️ YouTube',
                                    'tiktok' => '🎵 TikTok',
                                    'facebook' => '👥 Facebook',
                                    'linkedin' => '💼 LinkedIn',
                                    'x' => '𝕏 X (Twitter)',
                                    'pinterest' => '📌 Pinterest',
                                ])
                                ->columns(2)
                                ->bulkToggleable()
                                ->default(function ($livewire) {
                                    $values = $livewire->tableFilters['platform']['values'] ?? [];
                                    if (empty($values) && ! empty($livewire->tableFilters['platform']['value'])) {
                                        $values = [$livewire->tableFilters['platform']['value']];
                                    }
                                    return $values;
                                }),
                        ])
                        ->action(function ($livewire, array $data) {
                            $selected = $data['selected_platforms'] ?? [];
                            $filters = $livewire->tableFilters ?? [];
                            $filters['platform']['values'] = $selected;
                            $filters['platform']['value'] = null;
                            $livewire->tableFilters = $filters;
                            $livewire->getTableFiltersForm()->fill($livewire->tableFilters);
                            $livewire->resetPage();
                        }),
                ])
                ->label(function ($livewire) {
                    $values = $livewire->tableFilters['platform']['values'] ?? [];
                    if (empty($values) && ! empty($livewire->tableFilters['platform']['value'])) {
                        $values = [$livewire->tableFilters['platform']['value']];
                    }
                    if (empty($values)) {
                        return 'Platformlar';
                    }
                    if (count($values) === 1) {
                        return match ($values[0]) {
                            'instagram' => 'Platform: Instagram',
                            'youtube' => 'Platform: YouTube',
                            'tiktok' => 'Platform: TikTok',
                            'facebook' => 'Platform: Facebook',
                            'linkedin' => 'Platform: LinkedIn',
                            'x' => 'Platform: X',
                            'pinterest' => 'Platform: Pinterest',
                            default => 'Platform: ' . ucfirst($values[0]),
                        };
                    }
                    return 'Platformlar (' . count($values) . ')';
                })
                ->icon(function ($livewire) {
                    $values = $livewire->tableFilters['platform']['values'] ?? [];
                    if (empty($values) && ! empty($livewire->tableFilters['platform']['value'])) {
                        $values = [$livewire->tableFilters['platform']['value']];
                    }
                    if (count($values) === 1) {
                        return match ($values[0]) {
                            'instagram' => 'heroicon-m-camera',
                            'youtube' => 'heroicon-m-play-circle',
                            'tiktok' => 'heroicon-m-musical-note',
                            'facebook' => 'heroicon-m-user-group',
                            'linkedin' => 'heroicon-m-briefcase',
                            'x' => 'heroicon-m-hashtag',
                            'pinterest' => 'heroicon-m-bookmark',
                            default => 'heroicon-m-share',
                        };
                    }
                    return ! empty($values) ? 'heroicon-m-funnel' : 'heroicon-m-squares-2x2';
                })
                ->color(function ($livewire) {
                    $values = $livewire->tableFilters['platform']['values'] ?? [];
                    if (empty($values) && ! empty($livewire->tableFilters['platform']['value'])) {
                        $values = [$livewire->tableFilters['platform']['value']];
                    }
                    return ! empty($values) ? 'primary' : 'gray';
                })
                ->button()
                ->size('sm')
                ->tooltip('Platform Seç ve Filtrele'),

                Action::make('clear_platform_badge')
                    ->label('')
                    ->icon('heroicon-m-x-mark')
                    ->iconButton()
                    ->size('sm')
                    ->color('danger')
                    ->tooltip('Platform filtresini temizle')
                    ->visible(function ($livewire) {
                        $values = $livewire->tableFilters['platform']['values'] ?? [];
                        if (empty($values) && ! empty($livewire->tableFilters['platform']['value'])) {
                            $values = [$livewire->tableFilters['platform']['value']];
                        }
                        return ! empty($values);
                    })
                    ->action(function ($livewire) {
                        $filters = $livewire->tableFilters ?? [];
                        $filters['platform']['values'] = [];
                        $filters['platform']['value'] = null;
                        $livewire->tableFilters = $filters;
                        $livewire->getTableFiltersForm()->fill($livewire->tableFilters);
                        $livewire->resetPage();
                    }),

                Action::make('sync_thumbnails')
                    ->label('Önizlemeleri Senkronize Et')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->tooltip('Eksik video önizleme görsellerini senkronize et')
                    ->action(function () {
                        $service = app(\App\Services\Media\VideoProcessingService::class);
                        $videos = Video::all();
                        $fixed = 0;
                        foreach ($videos as $video) {
                            $disk = \Illuminate\Support\Facades\Storage::disk($video->disk ?? 'public');
                            $hasThumb = ! empty($video->thumbnail_path) && $disk->exists($video->thumbnail_path);
                            if (! $hasThumb && ! empty($video->storage_path) && $disk->exists($video->storage_path)) {
                                $newThumb = $service->generateThumbnail($video->storage_path, $video->duration_seconds);
                                if ($newThumb) {
                                    $video->update(['thumbnail_path' => $newThumb]);
                                    $fixed++;
                                }
                            }
                        }

                        Notification::make()
                            ->title('Önizleme Senkronizasyonu Tamamlandı')
                            ->body($fixed > 0 ? "{$fixed} videonun eksik kapak görseli başarıyla senkronize edildi." : 'Tüm videoların görselleri güncel.')
                            ->success()
                            ->send();
                    }),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->label('Platform')
                    ->multiple()
                    ->options([
                        'instagram' => 'Instagram',
                        'youtube' => 'YouTube',
                        'tiktok' => 'TikTok',
                        'facebook' => 'Facebook',
                        'linkedin' => 'LinkedIn',
                        'x' => 'X (Twitter)',
                        'pinterest' => 'Pinterest',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $values = [];
                        if (! empty($data['values'])) {
                            $values = (array) $data['values'];
                        } elseif (! empty($data['value'])) {
                            $values = [$data['value']];
                        }
                        if (! empty($values)) {
                            $query->whereHas('targets', fn ($q) => $q->whereIn('platform_code', $values));
                        }
                    }),
                SelectFilter::make('status')
                    ->label('Yayın Durumu')
                    ->options(collect(PublishStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actionsColumnLabel('İşlemler')
            ->actions([
                Action::make('publish_now')
                    ->label('Şimdi Gönder')
                    ->icon('heroicon-o-paper-airplane')
                    ->iconButton()
                    ->size('sm')
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
                    ->size('sm')
                    ->tooltip('Detayları Gör')
                    ->modalHeading('Yayın Hedefleri ve Detayları')
                    ->modalContent(fn (ScheduledPost $record) => view('filament.modals.scheduled-post-details', ['post' => $record])),

                Action::make('requeue_failed_targets')
                    ->label('Kuyruğa Al')
                    ->icon('heroicon-o-arrow-path')
                    ->iconButton()
                    ->size('sm')
                    ->color('warning')
                    ->tooltip('Bu videodaki başarısız hedefleri (örn: YouTube) yeniden kuyruğa alır.')
                    ->visible(fn (ScheduledPost $record): bool => $record->targets()->where('status', PublishStatus::FAILED)->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Başarısız Hedefleri Yeniden Kuyruğa Al')
                    ->modalDescription('Bu gönderideki başarısız platform hedefleri (YouTube vb.) sıfırlanıp yeniden kuyruğa alınacaktır. Gönderi statüsü güncellenecektir.')
                    ->action(function (ScheduledPost $record) {
                        $requeued = app(\App\Services\Publisher\SchedulingService::class)->requeueFailedTargets($record->id);

                        Notification::make()
                            ->title("{$requeued} Hedef Yeniden Kuyruğa Alındı!")
                            ->body('Başarısız hedefler sıfırlandı ve gönderi durumu güncellendi.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->size('sm'),
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
