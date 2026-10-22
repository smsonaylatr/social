<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Resources\VideoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVideos extends ListRecords
{
    protected static string $resource = VideoResource::class;
    protected static ?string $title = 'Video Havuzu & Medya Kütüphanesi';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Toplu Video Yükle')
                ->icon('heroicon-o-arrow-up-tray'),
        ];
    }
}
