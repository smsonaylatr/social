<?php

namespace App\Filament\Resources\VideoCategoryResource\Pages;

use App\Filament\Resources\VideoCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageVideoCategories extends ManageRecords
{
    protected static string $resource = VideoCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Yeni Kategori')
                ->icon('heroicon-o-plus')
                ->size('sm'),
        ];
    }
}
