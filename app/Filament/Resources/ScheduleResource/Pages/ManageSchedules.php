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
    }

    protected function getHeaderActions(): array
    {
        return [
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
