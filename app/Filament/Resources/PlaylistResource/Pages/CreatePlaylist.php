<?php

namespace App\Filament\Resources\PlaylistResource\Pages;

use App\Filament\Resources\PlaylistResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlaylist extends CreateRecord
{
    protected static string $resource = PlaylistResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['workspace_id'] = 1;
        return $data;
    }
}
