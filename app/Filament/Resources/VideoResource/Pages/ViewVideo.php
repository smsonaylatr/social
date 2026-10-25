<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Resources\VideoResource;
use App\Models\Video;
use App\Services\Media\VideoProcessingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewVideo extends ViewRecord
{
    protected static string $resource = VideoResource::class;
    protected static string $view = 'filament.resources.video-resource.pages.view-video';

    public array $platformCompatibilities = [];

    public function mount(int | string $record): void
    {
        parent::mount($record);

        /** @var Video $video */
        $video = $this->getRecord();
        $service = app(VideoProcessingService::class);

        $platforms = ['instagram', 'tiktok', 'youtube', 'facebook', 'x', 'pinterest', 'linkedin'];
        foreach ($platforms as $platform) {
            $this->platformCompatibilities[$platform] = $service->validatePlatformCompatibility($video, $platform);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reanalyze')
                ->label('Tekrar Analiz Et')
                ->icon('heroicon-o-arrow-path')
                ->size('sm')
                ->action(function () {
                    /** @var Video $video */
                    $video = $this->getRecord();
                    try {
                        $service = app(VideoProcessingService::class);
                        $metadata = $service->analyzeVideo($video->storage_path);
                        $video->update($metadata->toArray());

                        Notification::make()
                            ->title('Video başarıyla analiz edildi')
                            ->success()
                            ->send();

                        $this->redirect(VideoResource::getUrl('view', ['record' => $video->id]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Hata')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
