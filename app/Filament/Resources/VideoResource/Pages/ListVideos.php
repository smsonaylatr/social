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
            Actions\Action::make('reanalyze_all')
                ->label('Tüm Videoları Yeniden Analiz Et & Onar')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Tüm Videoları Yeniden Analiz Et')
                ->modalDescription('Kayıtlı tüm videoların süre, çözünürlük, format ve önizleme görselleri baştan taranacaktır.')
                ->action(function () {
                    $service = app(\App\Services\Media\VideoProcessingService::class);
                    $videos = \App\Models\Video::all();
                    $count = 0;
                    foreach ($videos as $video) {
                        try {
                            if ($video->storage_path && \Illuminate\Support\Facades\Storage::disk($video->disk ?? 'public')->exists($video->storage_path)) {
                                $metadata = $service->analyzeVideo($video->storage_path);
                                $data = $metadata->toArray();
                                if (! empty($video->thumbnail_path) && ! \Illuminate\Support\Facades\Storage::disk($video->disk ?? 'public')->exists($video->thumbnail_path)) {
                                    $data['thumbnail_path'] = null;
                                }
                                $video->update($data);
                                $count++;
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("Reanalyze video #{$video->id} error: " . $e->getMessage());
                        }
                    }
                    \Filament\Notifications\Notification::make()
                        ->title("{$count} video başarıyla yeniden analiz edildi.")
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('Toplu Video Yükle')
                ->icon('heroicon-o-arrow-up-tray'),
        ];
    }
}
