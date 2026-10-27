<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Resources\VideoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVideos extends ListRecords
{
    public string $layoutView = 'grid';

    public function mount(): void
    {
        parent::mount();
        $this->layoutView = session()->get('video_pool_layout_view', 'grid');
    }

    public function changeLayoutView(): void
    {
        $this->layoutView = $this->isListLayout() ? 'grid' : 'list';
        session()->put('video_pool_layout_view', $this->layoutView);
        $this->resetTable();
    }

    public function isGridLayout(): bool
    {
        return $this->layoutView === 'grid';
    }

    public function isListLayout(): bool
    {
        return $this->layoutView === 'list';
    }

    public function getLayoutView(): string
    {
        return $this->layoutView;
    }

    public function boot(): void
    {
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\Tables\View\TablesRenderHook::TOOLBAR_SEARCH_AFTER,
            fn () => view('filament.tables.toolbar.layout-toggle'),
            scopes: static::class,
        );
    }

    protected static string $resource = VideoResource::class;
    protected static ?string $title = 'Video Havuzu & Medya Kütüphanesi';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reanalyze_all')
                ->label('Videoları Onar')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->size('sm')
                ->tooltip('Kayıtlı tüm videoların süre, çözünürlük, format ve önizleme görsellerini baştan tarar ve onarır.')
                ->requiresConfirmation()
                ->modalHeading('Tüm Videoları Yeniden Analiz Et & Onar')
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

                    // Rename sequential if titles are hashes or numeric IDs
                    $orderedVideos = \App\Models\Video::orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();
                    $seq = 1;
                    foreach ($orderedVideos as $v) {
                        $title = trim($v->title);
                        $isHashOrId = ctype_digit($title)
                            || (strlen($title) >= 15 && ! str_contains($title, ' '))
                            || preg_match('/^(vid|img|pxl|mov|rec|screen|wp|tiktok)[-_0-9]/i', $title)
                            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}/i', $title);

                        if ($isHashOrId) {
                            $v->update(['title' => "{$seq}. Video"]);
                        }
                        $seq++;
                    }
                    \App\Models\AuditLog::record('video_reanalyzed', null, [
                        'count' => $count,
                        'description' => "{$count} video başarıyla yeniden analiz edildi.",
                    ]);

                    $this->dispatch('sf:sync-thumbnails', force: true);

                    \Filament\Notifications\Notification::make()
                        ->title("{$count} video başarıyla yeniden analiz edildi.")
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('Video Yükle')
                ->icon('heroicon-o-arrow-up-tray')
                ->size('sm'),
        ];
    }
}
