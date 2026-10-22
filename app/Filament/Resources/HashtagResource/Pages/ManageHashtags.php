<?php

namespace App\Filament\Resources\HashtagResource\Pages;

use App\Filament\Resources\HashtagResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageHashtags extends ManageRecords
{
    protected static string $resource = HashtagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tekil Hashtag Ekle')
                ->mutateFormDataUsing(function (array $data) {
                    $data['workspace_id'] = 1;
                    return $data;
                }),
        ];
    }
}
