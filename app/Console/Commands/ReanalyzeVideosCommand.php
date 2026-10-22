<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\Media\VideoProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReanalyzeVideosCommand extends Command
{
    protected $signature = 'video:reanalyze {--id= : Belirli bir video ID} {--force : Mevcut verileri zorla güncelle}';
    protected $description = 'Kayıtlı videoların teknik parametrelerini (süre, boyut, çözünürlük) ve önizleme görsellerini yeniden analiz eder';

    public function handle(VideoProcessingService $service): int
    {
        $id = $this->option('id');
        $force = (bool) $this->option('force');

        $query = Video::query();
        if ($id) {
            $query->where('id', $id);
        } elseif (! $force) {
            // Eğer zorla değilse, süresi 0 olan veya thumbnail eksik olanları öncelikle analiz et
            $query->where(function ($q) {
                $q->where('duration_seconds', 0)
                  ->orWhereNull('thumbnail_path');
            });
        }

        $videos = $query->get();
        $this->info("Toplam {$videos->count()} video analiz edilecek...");

        $success = 0;
        $failed = 0;

        foreach ($videos as $video) {
            if (empty($video->storage_path)) {
                $this->warn("Video #{$video->id} storage_path boş, atlanıyor.");
                $failed++;
                continue;
            }

            $disk = Storage::disk($video->disk ?? 'public');
            if (! $disk->exists($video->storage_path)) {
                $this->error("Video #{$video->id} dosyası bulunamadı: {$video->storage_path}");
                $failed++;
                continue;
            }

            try {
                $metadata = $service->analyzeVideo($video->storage_path);
                $video->update($metadata->toArray());
                $this->info("✓ Video #{$video->id} ({$video->title}) başarıyla analiz edildi. Süre: {$video->duration_seconds}s, Çözünürlük: {$video->width}x{$video->height}");
                $success++;
            } catch (\Throwable $e) {
                $this->error("✗ Video #{$video->id} hatası: " . $e->getMessage());
                $failed++;
            }
        }

        $this->info("Tamamlandı: {$success} başarılı, {$failed} başarısız.");
        return 0;
    }
}
