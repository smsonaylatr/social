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
                    ->label('Kaynak Video (Açıklama ve SEO bu videodan üretilir)')
                    ->relationship('video', 'title', fn ($query) => $query->orderBy('id', 'asc'))
                    ->optionsLimit(1000)
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('post_type')
                    ->label('Paylaşım Formatı')
                    ->options([
                        'video' => '🎬 Video Gönderisi (MP4 Dosyası ile)',
                        'text' => '📝 Metin Gönderisi (Videosuz - AI Açıklama, Link & Hashtag)',
                    ])
                    ->default('video')
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
        $getPlatformFilter = function ($component): array {
            try {
                $livewire = method_exists($component, 'getLivewire') ? $component->getLivewire() : null;
                if (! $livewire && method_exists($component, 'getTable')) {
                    $livewire = $component->getTable()->getLivewire();
                }
                if (! $livewire) {
                    return [];
                }
                $values = $livewire->tableFilters['platform']['values'] ?? [];
                if (empty($values) && ! empty($livewire->tableFilters['platform']['value'])) {
                    $values = [$livewire->tableFilters['platform']['value']];
                }
                return (array) $values;
            } catch (\Throwable) {
                return [];
            }
        };

        $setPlatformFilter = function ($component, array $values = []): void {
            try {
                $livewire = method_exists($component, 'getLivewire') ? $component->getLivewire() : null;
                if (! $livewire && method_exists($component, 'getTable')) {
                    $livewire = $component->getTable()->getLivewire();
                }
                if (! $livewire) {
                    return;
                }
                $filters = $livewire->tableFilters ?? [];
                $filters['platform']['values'] = $values;
                $filters['platform']['value'] = null;
                $livewire->tableFilters = $filters;
                $livewire->getTableFiltersForm()->fill($livewire->tableFilters);
                $livewire->resetPage();
            } catch (\Throwable) {}
        };

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['video', 'playlist', 'targets.socialAccount']))
            ->emptyStateHeading('Henüz Planlanmış veya Sıralı Kuyruk Yok')
            ->emptyStateDescription('Sağ üstteki yeşil "Otomatik Sırayla Kuyruk Oluştur" butonuna tıklayarak Video Havuzundaki tüm videolarınızı seçtiğiniz saat aralıklarıyla otomatik olarak sıraya dizebilirsiniz.')
            ->defaultSort('scheduled_at', 'asc')
            ->columns([
                ViewColumn::make('preview')
                    ->label('Önizleme')
                    ->view('filament.tables.columns.scheduled-post-preview')
                    ->grow(false)
                    ->action(
                        Action::make('preview_modal')
                            ->modalHeading(fn (ScheduledPost $record) => $record->post_type === 'text' ? '📝 Metin Gönderisi Detayları' : ($record->video?->title ?? 'Video Önizleme'))
                            ->modalWidth(fn (ScheduledPost $record) => $record->post_type === 'text' ? MaxWidth::ThreeExtraLarge : MaxWidth::Small)
                            ->modalContent(fn (ScheduledPost $record) => $record->post_type === 'text'
                                ? view('filament.modals.scheduled-post-details', ['post' => $record])
                                : view('filament.modals.video-player-modal', ['video' => $record->video])
                            )
                    ),

                TextColumn::make('post_type')
                    ->label('Tür')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'text' => '📝 Metin',
                        default => '🎬 Video',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'text' => 'info',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('video.title')
                    ->label('İçerik')
                    ->searchable()
                    ->wrap()
                    ->weight('bold')
                    ->getStateUsing(fn (ScheduledPost $record): string => $record->post_type === 'text'
                        ? '📝 Metin Gönderisi' . ($record->video?->title ? ' (' . $record->video->title . ')' : '')
                        : ($record->video?->title ?? '—')
                    )
                    ->description(fn (ScheduledPost $record): string => ($record->playlist?->name ? $record->playlist->name . ' • ' : '') . $record->scheduled_at->diffForHumans()),

                TextColumn::make('caption')
                    ->label('Açıklama')
                    ->getStateUsing(fn (ScheduledPost $record): string => $record->caption ?: '—')
                    ->limit(80)
                    ->tooltip(function (ScheduledPost $record): ?string {
                        $caption = $record->caption;
                        return ($caption && mb_strlen($caption) > 80) ? $caption : null;
                    })
                    ->wrap()
                    ->copyable()
                    ->copyMessage('Açıklama panoya kopyalandı')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('targets', fn ($tq) => $tq->where('caption', 'like', "%{$search}%"))
                                ->orWhereHas('video', fn ($vq) => $vq->where('custom_caption', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
                        });
                    }),

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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'x' => '𝕏 X',
                        'linkedin' => '💼 LinkedIn',
                        'facebook' => '👥 Facebook',
                        'instagram' => '📸 Instagram',
                        'youtube' => '▶️ YouTube',
                        'tiktok' => '🎵 TikTok',
                        'pinterest' => '📌 Pinterest',
                        default => ucfirst($state),
                    })
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
                        ->action(fn (Action $action) => $setPlatformFilter($action, [])),
                    Action::make('filter_instagram')
                        ->label('📸 Instagram')
                        ->action(fn (Action $action) => $setPlatformFilter($action, ['instagram'])),
                    Action::make('filter_youtube')
                        ->label('▶️ YouTube')
                        ->action(fn (Action $action) => $setPlatformFilter($action, ['youtube'])),
                    Action::make('filter_tiktok')
                        ->label('🎵 TikTok')
                        ->action(fn (Action $action) => $setPlatformFilter($action, ['tiktok'])),
                    Action::make('filter_facebook')
                        ->label('👥 Facebook')
                        ->action(fn (Action $action) => $setPlatformFilter($action, ['facebook'])),
                    Action::make('filter_linkedin')
                        ->label('💼 LinkedIn')
                        ->action(fn (Action $action) => $setPlatformFilter($action, ['linkedin'])),
                    Action::make('filter_x')
                        ->label('𝕏 X (Twitter)')
                        ->action(fn (Action $action) => $setPlatformFilter($action, ['x'])),
                    Action::make('filter_pinterest')
                        ->label('📌 Pinterest')
                        ->action(fn (Action $action) => $setPlatformFilter($action, ['pinterest'])),
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
                                ->default(fn (Action $action) => $getPlatformFilter($action)),
                        ])
                        ->action(fn (Action $action, array $data) => $setPlatformFilter($action, $data['selected_platforms'] ?? [])),
                ])
                ->label(function (ActionGroup $group) use ($getPlatformFilter) {
                    $values = $getPlatformFilter($group);
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
                ->icon(function (ActionGroup $group) use ($getPlatformFilter) {
                    $values = $getPlatformFilter($group);
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
                ->color(function (ActionGroup $group) use ($getPlatformFilter) {
                    $values = $getPlatformFilter($group);
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
                    ->visible(fn (Action $action) => ! empty($getPlatformFilter($action)))
                    ->action(fn (Action $action) => $setPlatformFilter($action, [])),

                Action::make('sync_thumbnails')
                    ->label('Önizlemeleri Senkronize Et & Onar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->tooltip('Eksik veya bozuk video önizleme görsellerini onar ve senkronize et')
                    ->action(function (\Livewire\Component $livewire) {
                        $service = app(\App\Services\Media\VideoProcessingService::class);
                        $videos = Video::all();
                        $fixed = 0;
                        foreach ($videos as $video) {
                            $disk = \Illuminate\Support\Facades\Storage::disk($video->disk ?? 'public');
                            $hasThumb = ! empty($video->thumbnail_path) && $disk->exists($video->thumbnail_path);

                            // 0 bayt veya bozuk dosyaları temizle
                            if ($hasThumb && @filesize($disk->path($video->thumbnail_path)) < 500) {
                                @$disk->delete($video->thumbnail_path);
                                $video->update(['thumbnail_path' => null]);
                                $hasThumb = false;
                            }

                            if (! $hasThumb && ! empty($video->storage_path) && $disk->exists($video->storage_path)) {
                                $newThumb = $service->generateThumbnail($video->storage_path, $video->duration_seconds);
                                if ($newThumb) {
                                    $video->update(['thumbnail_path' => $newThumb]);
                                    $fixed++;
                                }
                            }
                        }

                        $livewire->dispatch('sf:sync-thumbnails', force: true);

                        Notification::make()
                            ->title('Önizleme Onarımı ve Senkronizasyonu Başlatıldı')
                            ->body('Tüm videolar taranıyor; eksik ve hatalı görseller arka planda otomatik olarak düzeltiliyor.')
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
                        if ($record->targets()->count() === 0) {
                            app(\App\Services\Publisher\SchedulingService::class)->repairOrphanedPostsAndSchedules($record->id);
                            $record->refresh();
                        }

                        $record->update(['status' => PublishStatus::QUEUED]);

                        foreach ($record->targets as $target) {
                            if ($target->status === PublishStatus::PUBLISHED) {
                                continue;
                            }
                            $target->update(['status' => PublishStatus::QUEUED]);
                            try {
                                DispatchPlatformPostJob::dispatchSync($target->id, true);
                            } catch (\Throwable) {
                                // Handled inside DispatchPlatformPostJob
                            }
                        }

                        $record->refresh();
                        $publishedCount = $record->targets()->where('status', PublishStatus::PUBLISHED)->count();
                        $failedTargets = $record->targets()->whereIn('status', [PublishStatus::FAILED, PublishStatus::RETRYING])->get();

                        if ($publishedCount > 0) {
                            Notification::make()
                                ->title($record->post_type === 'text' ? 'Metin Gönderisi Paylaşıldı! ✨' : 'Video Gönderisi Paylaşıldı! 🚀')
                                ->body("{$publishedCount} kanala başarıyla yayınlandı.")
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
                                ->title($record->post_type === 'text' ? 'Metin Gönderisi İşleniyor 📝' : 'Yayın Görevi Yürütülüyor')
                                ->body('Hedefler kuyruğa alındı ve arka planda işleniyor.')
                                ->info()
                                ->send();
                        }
                    }),

                Action::make('view_details')
                    ->label('Detaylar')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->size('sm')
                    ->tooltip('Detayları & Açıklamayı Gör')
                    ->modalHeading(fn (ScheduledPost $record) => ($record->post_type === 'text' ? '📝 Metin Gönderisi' : ($record->video?->title ?? 'Gönderi')) . ' - Paylaşım ve Açıklama Detayları')
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->modalContent(function (ScheduledPost $record) {
                        if ($record->targets()->count() === 0) {
                            app(\App\Services\Publisher\SchedulingService::class)->repairOrphanedPostsAndSchedules($record->id);
                            $record->refresh();
                        }
                        return view('filament.modals.scheduled-post-details', ['post' => $record]);
                    }),

                Action::make('requeue_failed_targets')
                    ->label('Kuyruğa Al')
                    ->icon('heroicon-o-arrow-path')
                    ->iconButton()
                    ->size('sm')
                    ->color('warning')
                    ->tooltip('Bu gönderideki başarısız hedefleri yeniden kuyruğa alır.')
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
