<?php

namespace App\Filament\Resources\KeywordLibraryResource\Pages;

use App\Filament\Resources\KeywordLibraryResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageKeywordLibraries extends ManageRecords
{
    protected static string $resource = KeywordLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Yeni Anahtar Kelime Ekle')
                ->mutateFormDataUsing(function (array $data) {
                    $data['workspace_id'] = 1;
                    return $data;
                }),
        ];
    }
}
