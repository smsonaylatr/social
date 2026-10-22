<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Enums\VideoStatus;
use App\Filament\Resources\VideoResource;
use App\Models\Brand;
use App\Models\Video;
use App\Services\Media\VideoProcessingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateVideo extends CreateRecord
{
    protected static string $resource = VideoResource::class;
    protected static ?string $title = 'Toplu Video Yükleme';

    protected function getRedirectUrl(): string
    {
        return VideoResource::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(VideoProcessingService::class);
        $files = $data['video_files'] ?? [];

        if (is_string($files)) {
            $files = [$files];
        }

        $defaultBrand = Brand::first();
        $createdVideos = [];
        $duplicateCount = 0;
        $counter = Video::count() + 1;

        foreach ($files as $filePath) {
            $absolutePath = Storage::disk('public')->path($filePath);

            if (! file_exists($absolutePath)) {
                continue;
            }

            $hash = hash_file('sha256', $absolutePath);
            $existing = $service->findDuplicate($hash);

            if ($existing) {
                $duplicateCount++;
                continue;
            }

            // Naming: Sequential name "X. Video" if filename is a random hash
            $rawFilename = pathinfo($filePath, PATHINFO_FILENAME);
            $isHash = (strlen($rawFilename) >= 20 && ! str_contains($rawFilename, ' '));
            if ($isHash) {
                $cleanTitle = "{$counter}. Video";
            } else {
                $clean = trim(Str::title(str_replace(['_', '-'], ' ', $rawFilename)));
                $cleanTitle = ! empty($clean) ? $clean : "{$counter}. Video";
            }
            $counter++;

            try {
                $metadata = $service->analyzeVideo($filePath);
                $videoData = $metadata->toArray();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Video analizi hatası (' . $filePath . '): ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
                $videoData = [
                    'file_hash' => $hash,
                    'file_size' => filesize($absolutePath),
                    'duration_seconds' => 0,
                    'width' => 1080,
                    'height' => 1920,
                    'aspect_ratio' => '9:16',
                    'fps' => 30.0,
                    'mime_type' => 'video/mp4',
                    'thumbnail_path' => null,
                ];
            }

            $video = Video::create(array_merge($videoData, [
                'workspace_id' => 1,
                'brand_id' => $defaultBrand?->id,
                'title' => $cleanTitle,
                'original_filename' => basename($filePath),
                'storage_path' => $filePath,
                'disk' => 'public',
                'status' => VideoStatus::READY,
                'is_reusable' => true,
            ]));

            $createdVideos[] = $video;
        }

        $count = count($createdVideos);
        $msg = "{$count} adet video başarıyla havuza yüklendi ve teknik parametreleri analiz edildi.";
        if ($duplicateCount > 0) {
            $msg .= " ({$duplicateCount} adet mükerrer dosya atlandı).";
        }

        Notification::make()
            ->title('Yükleme Tamamlandı')
            ->body($msg)
            ->success()
            ->persistent()
            ->send();

        return ! empty($createdVideos) ? $createdVideos[0] : new Video();
    }
}
