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
            if (\Illuminate\Support\Facades\Schema::hasTable('schedules') && ! \Illuminate\Support\Facades\Schema::hasColumn('schedules', 'schedule_type')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable) {
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Yeni Otomasyon Kuralı Oluştur')
                ->mutateFormDataUsing(function (array $data) {
                    $data['workspace_id'] = 1;

                    try {
                        if (\Illuminate\Support\Facades\Schema::hasTable('schedules') && ! \Illuminate\Support\Facades\Schema::hasColumn('schedules', 'schedule_type')) {
                            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                        }
                    } catch (\Throwable) {
                    }

                    return $data;
                }),
        ];
    }
}
