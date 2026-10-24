<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\SocialPlatform;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SystemHealthPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-heart';
    protected static ?string $navigationGroup = 'Sistem Ayarları';
    protected static ?string $navigationLabel = 'Sistem Sağlık Paneli';
    protected static ?string $title = 'Sistem Sağlık ve Altyapı Kontrolü';
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.system-health-page';

    public array $healthData = [];

    public function mount(): void
    {
        $this->refreshHealth();
    }

    public function refreshHealth(): void
    {
        // 1. Database
        $dbStart = microtime(true);
        $dbOk = false;
        $dbError = null;
        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }
        $dbDuration = round((microtime(true) - $dbStart) * 1000, 2);

        // 2. Storage
        $storageOk = false;
        try {
            $testFile = 'health_' . time() . '.txt';
            Storage::disk('public')->put($testFile, 'OK');
            $storageOk = Storage::disk('public')->exists($testFile);
            Storage::disk('public')->delete($testFile);
        } catch (\Throwable $e) {
            $storageOk = false;
        }

        // 3. Queue & Jobs
        $failedJobs = DB::table('failed_jobs')->count();
        $pendingJobs = DB::table('jobs')->count();

        // 4. FFmpeg
        $ffmpegPath = Setting::get('ffmpeg_binary_path', 'ffmpeg');
        $ffmpegFound = false;
        $ffmpegVersion = 'Bulunamadı';
        try {
            $out = @shell_exec("{$ffmpegPath} -version 2>&1");
            if ($out && str_contains(strtolower($out), 'ffmpeg version')) {
                $ffmpegFound = true;
                $lines = explode("\n", $out);
                $ffmpegVersion = $lines[0] ?? 'FFmpeg Ready';
            }
        } catch (\Throwable $e) {
            $ffmpegFound = false;
        }

        // 5. OpenAI
        $aiKeyConfigured = ! empty(config('services.openai.api_key')) || ! empty(env('OPENAI_API_KEY')) || ! empty(Setting::get('openai_api_key'));

        // 6. Social Platforms
        $platforms = SocialPlatform::withCount(['accounts as active_accounts_count' => function ($q) {
            $q->where('status', 'active');
        }])->get();

        $this->healthData = [
            'database' => [
                'status' => $dbOk,
                'latency' => $dbDuration,
                'error' => $dbError,
            ],
            'storage' => [
                'status' => $storageOk,
                'driver' => config('filesystems.default'),
            ],
            'queue' => [
                'status' => $failedJobs === 0,
                'pending' => $pendingJobs,
                'failed' => $failedJobs,
            ],
            'ffmpeg' => [
                'status' => $ffmpegFound,
                'version' => $ffmpegVersion,
            ],
            'ai' => [
                'status' => $aiKeyConfigured,
                'model' => Setting::get('openai_model', 'gpt-4o-mini'),
            ],
            'platforms' => $platforms->toArray(),
        ];
    }

    public function clearSystemCache(): void
    {
        Artisan::call('optimize:clear');
        Notification::make()->title('Sistem önbellekleri temizlendi')->success()->send();
        $this->refreshHealth();
    }
}
