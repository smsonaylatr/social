<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCampaigns extends ManageRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Yeni Kampanya Oluştur')
                ->mutateFormDataUsing(function (array $data) {
                    $data['workspace_id'] = 1;
                    return $data;
                }),
        ];
    }
}
