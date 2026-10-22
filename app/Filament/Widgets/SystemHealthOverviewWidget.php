<?php

namespace App\Filament\Widgets;

use App\Models\Setting;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SystemHealthOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.system-health-overview-widget';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $pollingInterval = '60s';

    public function getViewData(): array
    {
        // 1. Database Check
        $dbStatus = false;
        try {
            DB::connection()->getPdo();
            $dbStatus = true;
        } catch (\Throwable $e) {
            $dbStatus = false;
        }

        // 2. Storage Check
        $storageStatus = false;
        try {
            $testFile = 'health_check_' . time() . '.txt';
            Storage::disk('public')->put($testFile, 'OK');
            $storageStatus = Storage::disk('public')->exists($testFile);
            Storage::disk('public')->delete($testFile);
        } catch (\Throwable $e) {
            $storageStatus = false;
        }

        // 3. Queue Check (Failed jobs count)
        $failedJobsCount = DB::table('failed_jobs')->count();
        $pendingJobsCount = DB::table('jobs')->count();

        // 4. FFmpeg Check
        $ffmpegPath = Setting::get('ffmpeg_binary_path', 'ffmpeg');
        $ffmpegFound = false;
        try {
            $output = @shell_exec("{$ffmpegPath} -version 2>&1");
            if ($output && str_contains(strtolower($output), 'ffmpeg version')) {
                $ffmpegFound = true;
            }
        } catch (\Throwable $e) {
            $ffmpegFound = false;
        }

        // 5. OpenAI API Key configured?
        $openAiKeyConfigured = ! empty(env('OPENAI_API_KEY')) || ! empty(Setting::get('openai_api_key'));

        return [
            'services' => [
                [
                    'name' => 'Veritabanı (MySQL)',
                    'status' => $dbStatus ? 'Online' : 'Hata',
                    'is_healthy' => $dbStatus,
                    'detail' => $dbStatus ? 'Aktif bağlantı hazır' : 'Veritabanına ulaşılamıyor',
                    'icon' => 'heroicon-o-circle-stack',
                ],
                [
                    'name' => 'Depolama Alanı (Storage)',
                    'status' => $storageStatus ? 'Hazır' : 'Yazma Hatası',
                    'is_healthy' => $storageStatus,
                    'detail' => 'public disk okuma/yazma testi başarılı',
                    'icon' => 'heroicon-o-folder',
                ],
                [
                    'name' => 'Kuyruk Motoru (Queue)',
                    'status' => $failedJobsCount > 0 ? 'Uyarı' : 'Aktif',
                    'is_healthy' => $failedJobsCount === 0,
                    'detail' => "{$pendingJobsCount} bekleyen, {$failedJobsCount} başarısız iş",
                    'icon' => 'heroicon-o-queue-list',
                ],
                [
                    'name' => 'FFmpeg Video Motoru',
                    'status' => $ffmpegFound ? 'Kurulu' : 'Beklemede',
                    'is_healthy' => $ffmpegFound,
                    'detail' => $ffmpegFound ? 'Video analiz & thumbnail aktif' : 'FFmpeg yolu doğrulanmalı',
                    'icon' => 'heroicon-o-film',
                ],
                [
                    'name' => 'AI Motoru (OpenAI)',
                    'status' => $openAiKeyConfigured ? 'Anahtar Tanımlı' : 'Yapılandırma Bekleniyor',
                    'is_healthy' => $openAiKeyConfigured,
                    'detail' => $openAiKeyConfigured ? 'GPT-4o metin üretimi hazır' : 'API anahtarı girildiğinde aktifleşecek',
                    'icon' => 'heroicon-o-sparkles',
                ],
            ],
        ];
    }
}
