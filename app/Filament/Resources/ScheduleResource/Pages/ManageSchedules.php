<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSchedules extends ManageRecords
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Yeni Otomasyon Kuralı Oluştur')
                ->mutateFormDataUsing(function (array $data) {
                    $data['workspace_id'] = 1;
                    return $data;
                }),
        ];
    }
}
