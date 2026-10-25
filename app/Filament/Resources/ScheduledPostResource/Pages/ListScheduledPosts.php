<?php

namespace App\Filament\Resources\ScheduledPostResource\Pages;

use App\Filament\Resources\ScheduledPostResource;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Services\Automation\AutomationOrchestratorService;
use App\Services\Publisher\SchedulingService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListScheduledPosts extends ListRecords
{
    protected static string $resource = ScheduledPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('requeue_all_failed')
                ->label('Başarısızları Kuyruğa Al')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->size('sm')
                ->tooltip('Kısmen başarılı olan gönderilerdeki YouTube vb. başarısız hedefleri yeniden kuyruğa alır.')
                ->requiresConfirmation()
                ->modalHeading('Tüm Başarısız Platform Hedeflerini Kuyruğa Al')
                ->modalDescription('Günlük yükleme kotası (uploadLimitExceeded) veya geçici API hataları nedeniyle başarısız olmuş YouTube ve diğer platform hedeflerini yeniden kuyruğa alır. Gönderilerin statüleri "Kuyrukta" olarak düzeltilir.')
                ->modalSubmitActionLabel('Başarısızları Kuyruğa Ekle')
                ->action(function () {
                    $requeued = app(\App\Services\Publisher\SchedulingService::class)->requeueFailedTargets();

                    if ($requeued > 0) {
                        Notification::make()
                            ->title("{$requeued} Başarısız Hedef Yeniden Kuyruğa Alındı! 🚀")
                            ->body('Başarısız YouTube ve diğer platform hedefleri kuyruğa eklendi, gönderi statüleri güncellendi.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Başarısız Hedef Bulunamadı')
                            ->body('Kuyruğa alınacak başarısız hedef bulunmuyor.')
                            ->info()
                            ->send();
                    }
                }),

            Actions\Action::make('reschedule_algorithmic')
                ->label('Keşfete Dağıt')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->size('sm')
                ->tooltip('Kuyruktaki bekleyen tüm videoları günün en çok izlenen 5 Zirve Dalgasına yeniden dağıtır.')
                ->requiresConfirmation()
                ->modalHeading('Tüm Bekleyen Kuyruğu Keşfet Dalgalarına Yeniden Dağıt')
                ->modalDescription('Kuyruktaki bekleyen tüm videoları 5 dakikalık sıkışık aralıklardan çıkarıp, her platformun en çok izlendiği 5 Zirve Dalgasına (09:30 • 12:45 • 16:15 • 19:15 • 22:00) ve platform kotalarına uygun biçimde yeniden planlar.')
                ->modalSubmitActionLabel('Kuyruğu Algoritmaya Göre Güncelle')
                ->action(function () {
                    $orchestrator = app(AutomationOrchestratorService::class);
                    $count = $orchestrator->reschedulePendingPostsToAlgorithmicWaves(Carbon::now());

                    if ($count > 0) {
                        Notification::make()
                            ->title('Kuyruk Keşfet Dalgalarına Göre Güncellendi! 🚀')
                            ->body("Toplam {$count} adet planlanmış video, platform izlenme zirvelerine göre yeniden dağıtıldı.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Kuyrukta Bekleyen Gönderi Bulunamadı')
                            ->body('Yeniden planlanacak bekleyen ("scheduled" veya "queued") gönderi bulunmuyor.')
                            ->info()
                            ->send();
                    }
                }),

            Actions\Action::make('auto_sequential_queue')
                ->label('Sıralı Kuyruk')
                ->icon('heroicon-o-queue-list')
                ->color('success')
                ->size('sm')
                ->tooltip('Video havuzundaki videoları otomatik sırayla paylaşım takvimine ve kuyruğa ekler.')
                ->modalHeading('Videoları Otomatik Sırayla Kuyruğa & Takvime Diz')
                ->modalDescription('Video havuzundaki videolarınızı tek tek seçmek yerine belirlediğiniz sırayla ve zaman aralığıyla otomatik olarak paylaşım kuyruğuna dizer.')
                ->modalSubmitActionLabel('Sıralı Kuyruğu Başlat')
                ->form([
                    Grid::make(['default' => 1, 'sm' => 2])
                        ->schema([
                            Select::make('order_mode')
                                ->label('Sıralama ve Kaynak Modu')
                                ->options([
                                    'sequential' => 'Sırayla Paylaş (Video 1 → 2 → 3 Yükleme Sırası)',
                                    'least_used' => 'Döngüsel Sırayla (Hiç Yayınlanmayanlar Önce, Sonra En Eski Yayınlananlar)',
                                    'newest_first' => 'En Yeni Videodan Eskiye Doğru Sırayla',
                                    'shuffle' => 'Karışık (Rastgele) Sırayla',
                                    'playlist' => 'Özel Oynatma Listesinden Sırayla',
                                ])
                                ->default('sequential')
                                ->live()
                                ->required(),

                            Select::make('playlist_id')
                                ->label('Oynatma Listesi Seçin')
                                ->options(fn () => Playlist::pluck('name', 'id'))
                                ->visible(fn (Get $get): bool => $get('order_mode') === 'playlist')
                                ->required(fn (Get $get): bool => $get('order_mode') === 'playlist'),

                            DateTimePicker::make('start_at')
                                ->label('İlk Videonun Paylaşım Zamanı')
                                ->default(now()->addMinutes(5))
                                ->seconds(false)
                                ->required(),

                            Select::make('interval_minutes')
                                ->label('Videolar Arası Otomatik Paylaşım Sıklığı')
                                ->options([
                                    0 => '🚀 Algoritmik Keşfet Modu (Günde 5 Zirve Dalgası: 09:30 • 12:45 • 16:15 • 19:15 • 22:00)',
                                    15 => 'Her 15 Dakikada 1 Video (Manuel Test)',
                                    30 => 'Her 30 Dakikada 1 Video',
                                    60 => 'Her 1 Saatte 1 Video',
                                    120 => 'Her 2 Saatte 1 Video',
                                    180 => 'Her 3 Saatte 1 Video',
                                    360 => 'Her 6 Saatte 1 Video (Günde 4 Video)',
                                    720 => 'Her 12 Saatte 1 Video (Günde 2 Video)',
                                    1440 => 'Her 24 Saatte 1 Video (Her Gün Aynı Saatte)',
                                ])
                                ->default(0)
                                ->required(),

                            TextInput::make('limit')
                                ->label('Kuyruğa Alınacak Video Sayısı (Opsiyonel)')
                                ->placeholder('Boş bırakılırsa tüm hazır videolar sırayla eklenir')
                                ->numeric()
                                ->minValue(1),
                        ]),

                    CheckboxList::make('social_account_ids')
                        ->label('Paylaşım Yapılacak Sosyal Medya Hesapları')
                        ->options(fn () => SocialAccount::with('platform')->get()->mapWithKeys(fn ($a) => [
                            $a->id => ($a->platform?->name ?? strtoupper($a->platform_id)) . " — {$a->name} ({$a->account_type})",
                        ]))
                        ->columns(2)
                        ->bulkToggleable()
                        ->required(),

                    Toggle::make('create_recurring_rule')
                        ->label('Sürekli Otomatik Yayın Kuralı Olarak da Aktif Et')
                        ->helperText('Etkinleştirildiğinde yeni yüklediğiniz videolar da belirlediğiniz saatte otomatik sırayla paylaşılmaya devam eder.')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    $orchestrator = app(AutomationOrchestratorService::class);
                    $startAt = Carbon::parse($data['start_at']);
                    $accountIds = $data['social_account_ids'] ?? [];
                    $interval = (int) $data['interval_minutes'];

                    $count = $orchestrator->buildSequentialQueue(
                        socialAccountIds: $accountIds,
                        startAt: $startAt,
                        intervalMinutes: $interval,
                        orderMode: $data['order_mode'],
                        playlistId: $data['playlist_id'] ?? null,
                        limit: ! empty($data['limit']) ? (int) $data['limit'] : null,
                    );

                    if (! empty($data['create_recurring_rule'])) {
                        $isAlgorithmic = ($interval === 0);
                        $postsPerDay = $isAlgorithmic ? 5 : max(1, (int) floor(1440 / max(15, $interval)));

                        Schedule::create([
                            'workspace_id' => 1,
                            'brand_id' => 1,
                            'playlist_id' => $data['playlist_id'] ?? null,
                            'name' => $isAlgorithmic ? 'Algoritmik Keşfet Zirve Yayını (Günde 5 Dalga)' : ('Otomatik Sıralı Yayın (' . $startAt->format('H:i') . ')'),
                            'time_of_day' => $startAt->format('H:i:00'),
                            'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
                            'target_social_account_ids' => $accountIds,
                            'growth_strategy' => $isAlgorithmic ? 'algorithmic_growth' : 'fixed_time',
                            'anti_spam_shield' => $isAlgorithmic,
                            'enable_smart_jitter' => $isAlgorithmic,
                            'enable_staggered_dispatch' => $isAlgorithmic,
                            'max_posts_per_day' => $postsPerDay,
                            'is_active' => true,
                        ]);
                    }

                    if ($count > 0) {
                        Notification::make()
                            ->title('Otomatik Sıralı Kuyruk Oluşturuldu!')
                            ->body("Toplam {$count} adet video sırayla paylaşım takvimine ve kuyruğa eklendi.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Havuzda Hazır Video Bulunamadı')
                            ->body('Önce "Video Havuzu" menüsünden videolarınızı yükleyin. Sürekli otomasyon kuralı seçtiyseniz kuralınız kaydedildi.')
                            ->warning()
                            ->send();
                    }
                }),

            Actions\Action::make('publish_next_in_sequence')
                ->label('Sıradakini Paylaş')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->size('sm')
                ->tooltip('Video havuzunda sırası gelen bir sonraki videoyu seçip anında gönderir.')
                ->modalHeading('Sıradaki Videoyu Anında Paylaş')
                ->modalDescription('Video havuzunda sırası gelen (henüz yayınlanmamış veya en eski yayınlanmış) bir sonraki videoyu otomatik seçip seçtiğiniz hesaplara şimdi gönderir.')
                ->form([
                    CheckboxList::make('social_account_ids')
                        ->label('Gönderilecek Sosyal Medya Hesapları')
                        ->options(fn () => SocialAccount::with('platform')->get()->mapWithKeys(fn ($a) => [
                            $a->id => ($a->platform?->name ?? strtoupper($a->platform_id)) . " — {$a->name}",
                        ]))
                        ->columns(2)
                        ->bulkToggleable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $lastScheduledVideoId = \App\Models\ScheduledPost::whereNotNull('video_id')->orderBy('id', 'desc')->value('video_id') ?? 0;
                    $video = Video::whereIn('status', ['ready', 'active', 'published'])
                        ->orderByRaw('id > ? DESC, id ASC', [$lastScheduledVideoId])
                        ->first();

                    if (! $video) {
                        $video = Video::orderByRaw('id > ? DESC, id ASC', [$lastScheduledVideoId])->first();
                    }

                    if (! $video) {
                        Notification::make()
                            ->title('Yayınlanacak Video Bulunamadı')
                            ->body('Video Havuzunda yayına hazır video bulunmuyor.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $video->update(['last_used_at' => now()]);
                    $service = app(SchedulingService::class);
                    $service->createScheduledPost(
                        video: $video,
                        scheduledAt: now(),
                        socialAccountIds: $data['social_account_ids'] ?? []
                    );
                    $service->dispatchDuePosts();

                    Notification::make()
                        ->title('Sıradaki Video Kuyruğa Alındı!')
                        ->body("'{$video->title}' videosu seçilen hesaplara şimdi gönderiliyor.")
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('Tekil Planla')
                ->icon('heroicon-o-plus')
                ->size('sm'),
        ];
    }
}
