<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSchedules extends ManageRecords
{
    protected static string $resource = ScheduleResource::class;

    public function mount(): void
    {
        parent::mount();

        try {
            \App\Models\Schedule::all()->each(function (\App\Models\Schedule $s) {
                $clean = ScheduleResource::formatPlatformName($s->name);
                $update = [];
                if ($clean !== $s->name) {
                    $update['name'] = $clean;
                }
                if ($s->playlist_id !== null) {
                    $update['playlist_id'] = null;
                }
                if (!empty($update)) {
                    $s->update($update);
                }
            });
        } catch (\Throwable) {}
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('setup_platform_rules')
                ->label('⚡ Her Platforma Özel Kural Oluştur')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->size('sm')
                ->tooltip('Instagram, TikTok, YouTube Shorts, Facebook, X, LinkedIn ve Pinterest için ayrı ayrı optimize edilmiş 7 zirve keşfet kuralını otomatik oluşturur.')
                ->requiresConfirmation()
                ->modalHeading('Tüm Platformlara Özel Ayrık Keşfet Kuralları Oluştur')
                ->modalDescription('Her mecra için bağımsız, spam engeline takılmayan ve o platformun en çok izlendiği saatlerde çalışan 7 adet otomasyon kuralı sisteme eklenecektir.')
                ->modalSubmitActionLabel('Platform Kurallarını Kur')
                ->action(function () {
                    $orchestrator = app(\App\Services\Automation\AutomationOrchestratorService::class);
                    $rules = $orchestrator->setupDefaultPlatformSpecificRules();
                    $count = count($rules);

                    \Filament\Notifications\Notification::make()
                        ->title("{$count} Platform İçin Özel Keşfet Kuralları Oluşturuldu! 🚀")
                        ->body('Instagram, TikTok, YouTube, Facebook, X, LinkedIn ve Pinterest kuralları bağımsız zamanlamalarıyla hazır.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('trigger_cron_now')
                ->label('Şimdi Çalıştır')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->size('sm')
                ->tooltip('Zamanı gelen tüm kuralları ve bekleyen kuyruk gönderilerini anında tetikler.')
                ->action(function () {
                    $orchestrator = app(\App\Services\Automation\AutomationOrchestratorService::class);
                    $schedulingService = app(\App\Services\Publisher\SchedulingService::class);

                    $executed = $orchestrator->checkAndRunDueSchedules();
                    $dispatched = $schedulingService->dispatchDuePosts();

                    try {
                        \Illuminate\Support\Facades\Artisan::call('queue:work', [
                            '--stop-when-empty' => true,
                            '--max-jobs' => 20,
                            '--tries' => 3,
                        ]);
                    } catch (\Throwable) {}

                    \Filament\Notifications\Notification::make()
                        ->title('Otomasyon Kontrolü Tamamlandı')
                        ->body("{$executed} adet kural tetiklendi, {$dispatched} adet gönderi işleme alındı.")
                        ->info()
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('Yeni Kural')
                ->icon('heroicon-o-plus')
                ->size('sm')
                ->modalWidth(\Filament\Support\Enums\MaxWidth::ThreeExtraLarge)
                ->mutateFormDataUsing(function (array $data) {
                    $data['workspace_id'] = 1;
                    return $data;
                }),
        ];
    }
}
