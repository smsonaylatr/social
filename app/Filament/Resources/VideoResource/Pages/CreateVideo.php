<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Resources\VideoResource;
use App\Models\Video;
use App\Services\Media\VideoProcessingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateVideo extends CreateRecord
{
    protected static string $resource = VideoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $service = app(VideoProcessingService::class);
        $storagePath = $data['storage_path'] ?? null;

        if ($storagePath) {
            $absolutePath = Storage::disk('public')->path($storagePath);

            if (file_exists($absolutePath)) {
                $hash = hash_file('sha256', $absolutePath);
                $existingDuplicate = $service->findDuplicate($hash);

                if ($existingDuplicate) {
                    Notification::make()
                        ->title('Yinelenen Video Tespit Edildi!')
                        ->body("Bu video dosyası daha önce '{$existingDuplicate->title}' adıyla yüklenmiş (Hash: " . substr($hash, 0, 10) . "...).")
                        ->warning()
                        ->persistent()
                        ->send();
                }

                $metadata = $service->analyzeVideo($storagePath);
                $data = array_merge($data, $metadata->toArray());
                $data['original_filename'] = basename($storagePath);
            }
        }

        $data['workspace_id'] = 1; // Default Workspace

        return $data;
    }
}
