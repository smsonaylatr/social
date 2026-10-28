<?php

use App\Http\Controllers\DeployWebhookController;
use App\Http\Controllers\SocialOAuthController;
use App\Http\Controllers\SocialWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Hızlı Menü & Yayın Yönlendirmeleri
Route::get('/admin/yayin-ve-otomasyon', fn () => redirect('/admin/schedules'));
Route::get('/admin/automation', fn () => redirect('/admin/schedules'));
Route::get('/admin/canli-yayin', fn () => redirect('/admin/live-streams'));
Route::get('/admin/live', fn () => redirect('/admin/live-streams'));

// Legal Pages for OAuth & API Verification (Publicly accessible)
Route::get('/tiktok-developers-site-verification.txt', function () {
    return response('tiktok-developers-site-verification=7ohyfCDIRy2UtuKp9g9A4V9kEd0Dd1EV', 200)
        ->header('Content-Type', 'text/plain');
});
Route::get('/privacy-policy', fn () => view('legal.privacy'))->name('privacy.policy');
Route::get('/privacy', fn () => redirect()->route('privacy.policy'));
Route::get('/terms-of-service', fn () => view('legal.terms'))->name('terms.service');
Route::get('/terms', fn () => redirect()->route('terms.service'));

Route::match(['get', 'post'], '/admin/run-migrate', function () {
    $outputs = [];
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $outputs[] = \Illuminate\Support\Facades\Artisan::output();
    } catch (\Throwable $e) {
        $outputs[] = "MIGRATE ERR: " . $e->getMessage();
    }

    if (config('database.default') === 'sqlite') {
        try {
            \Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 60000;');
            \Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode = WAL;');
            \Illuminate\Support\Facades\DB::statement('PRAGMA synchronous = NORMAL;');
            $outputs[] = "SQLite WAL mode and busy_timeout=60000 activated.";
        } catch (\Throwable $e) {
            $outputs[] = "SQLite PRAGMA: " . $e->getMessage();
        }
    }

    try {
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        $outputs[] = "Cache cleared.";
    } catch (\Throwable $e) {}

    return response()->json([
        'status' => 'ok',
        'outputs' => $outputs,
    ]);
});

Route::match(['get', 'post'], '/admin/ffmpeg-check', function () {
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $hasShellExec = function_exists('shell_exec') && ! in_array('shell_exec', $disabled, true);
    $hasExec = function_exists('exec') && ! in_array('exec', $disabled, true);

    $whichFfmpeg = $hasShellExec ? trim((string) @shell_exec('which ffmpeg 2>/dev/null')) : null;
    $versionOutput = $hasShellExec ? trim((string) @shell_exec('ffmpeg -version 2>&1')) : null;

    $candidates = [
        storage_path('app/bin/ffmpeg'),
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/bin/ffmpeg',
        '/snap/bin/ffmpeg',
        '/opt/ffmpeg/bin/ffmpeg',
        '/usr/local/cpanel/3rdparty/bin/ffmpeg',
    ];

    $foundPaths = [];
    foreach ($candidates as $p) {
        if (@file_exists($p)) {
            $foundPaths[] = $p;
        }
    }

    $sudoCheck = $hasShellExec ? trim((string) @shell_exec('which sudo 2>/dev/null')) : null;
    $aptCheck = $hasShellExec ? trim((string) @shell_exec('which apt-get 2>/dev/null')) : null;

    return response()->json([
        'os' => PHP_OS,
        'os_family' => PHP_OS_FAMILY,
        'uname' => php_uname(),
        'shell_exec_available' => $hasShellExec,
        'exec_available' => $hasExec,
        'disabled_functions' => $disabled,
        'which_ffmpeg' => $whichFfmpeg,
        'version_first_line' => $versionOutput ? explode("\n", $versionOutput)[0] : null,
        'found_binary_paths' => $foundPaths,
        'has_apt' => !empty($aptCheck),
        'has_sudo' => !empty($sudoCheck),
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

Route::match(['get', 'post'], '/admin/ffmpeg-install', function () {
    @set_time_limit(180);
    $result = app(\App\Services\LiveStream\LiveStreamService::class)->autoInstallFfmpeg();

    return response()->json($result);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

Route::match(['get', 'post'], '/admin/stream-status-check', function () {
    $streams = \App\Models\LiveStream::all();
    $service = app(\App\Services\LiveStream\LiveStreamService::class);
    $data = [];

    foreach ($streams as $stream) {
        $logFile = $service->getLogFilePath($stream);
        $playlistFile = storage_path("app/live-streams/stream_{$stream->id}_playlist.txt");
        $isRunning = $stream->process_pid ? $service->isProcessRunning($stream->process_pid) : false;

        $logTail = '';
        if (file_exists($logFile)) {
            $logTail = $service->getRecentLogs($stream, 50);
        }

        $playlistContent = '';
        if (file_exists($playlistFile)) {
            $playlistContent = file_get_contents($playlistFile);
        }

        $data[] = [
            'id' => $stream->id,
            'title' => $stream->title,
            'target_platform' => $stream->target_platform,
            'status' => $stream->status,
            'process_pid' => $stream->process_pid,
            'is_running' => $isRunning,
            'last_error' => $stream->last_error,
            'playlist_exists' => file_exists($playlistFile),
            'playlist_sample' => substr($playlistContent, 0, 500),
            'log_tail' => $logTail,
        ];
    }

    return response()->json([
        'time' => now()->toDateTimeString(),
        'streams' => $data,
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);


Route::match(['get', 'post'], '/admin/requeue-failed', function () {
    $service = app(\App\Services\Publisher\SchedulingService::class);
    $requeued = $service->requeueFailedTargets();
    return response()->json([
        'status' => 'ok',
        'message' => "Toplam {$requeued} adet başarısız hedef (YouTube vb.) yeniden kuyruğa alındı ve gönderi durumları güncellendi.",
        'requeued_count' => $requeued,
    ]);
});

Route::match(['get', 'post'], '/admin/setup-platform-rules', function () {
    $orchestrator = app(\App\Services\Automation\AutomationOrchestratorService::class);
    $rules = $orchestrator->setupDefaultPlatformSpecificRules();
    return response()->json([
        'status' => 'ok',
        'message' => count($rules) . ' platform için bağımsız algoritmik keşfet kuralları oluşturuldu.',
        'rules' => $rules,
    ]);
});

Route::match(['get', 'post'], '/admin/reschedule-algorithmic', function () {
    $orchestrator = app(\App\Services\Automation\AutomationOrchestratorService::class);
    $count = $orchestrator->reschedulePendingPostsToAlgorithmicWaves(\Carbon\Carbon::now());
    return response()->json([
        'status' => 'ok',
        'message' => "Toplam {$count} adet bekleyen gönderi 5 Zirve Algoritmik Keşfet Dalgasına başarıyla yeniden dağıtıldı.",
        'rescheduled_posts_count' => $count,
    ]);
});

Route::match(['get', 'post'], '/admin/quick-fix', function () {
    $service = app(\App\Services\Media\VideoProcessingService::class);
    $videos = \App\Models\Video::all();
    $count = 0;
    $errors = [];

    // Ensure storage link
    if (! file_exists(public_path('storage'))) {
        @\Illuminate\Support\Facades\Artisan::call('storage:link');
    }

    // Clean only empty/corrupted 0-byte thumbnail files
    $thumbnailFiles = \Illuminate\Support\Facades\Storage::disk('public')->files('thumbnails');
    foreach ($thumbnailFiles as $file) {
        $size = \Illuminate\Support\Facades\Storage::disk('public')->size($file);
        if ($size === 0) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($file);
        }
    }

    foreach ($videos as $video) {
        try {
            if ($video->storage_path && \Illuminate\Support\Facades\Storage::disk($video->disk ?? 'public')->exists($video->storage_path)) {
                $metadata = $service->analyzeVideo($video->storage_path);
                $updateData = $metadata->toArray();

                // If thumbnail does not exist on disk, clear thumbnail_path so frontend captures fresh frame
                if (! empty($video->thumbnail_path) && ! \Illuminate\Support\Facades\Storage::disk($video->disk ?? 'public')->exists($video->thumbnail_path)) {
                    $updateData['thumbnail_path'] = null;
                }

                $video->update($updateData);
                $count++;
            }
        } catch (\Throwable $e) {
            $errors[] = "Video #{$video->id}: " . $e->getMessage();
        }
    }

    // Name all existing videos sequentially by upload order: "1. Video", "2. Video", ...
    $orderedVideos = \App\Models\Video::orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();
    $seq = 1;
    foreach ($orderedVideos as $v) {
        $v->update(['title' => "{$seq}. Video"]);
        $seq++;
    }

    $msg = "{$count} adet video başarıyla analiz edildi, önizlemeleri ve başlıkları (1. Video, 2. Video...) güncellendi.";
    if (! empty($errors)) {
        $msg .= " Hatalar: " . implode(', ', array_slice($errors, 0, 3));
    }

    return redirect('/admin/videos')->with('status', $msg);
})->name('admin.quick-fix');

Route::get('/quick-fix', fn () => redirect('/admin/quick-fix'));

// Client-side auto-captured thumbnail receiver
Route::post('/admin/videos/{id}/save-thumbnail', function (\Illuminate\Http\Request $request, int $id) {
    $video = \App\Models\Video::find($id);
    if (! $video) {
        return response()->json(['error' => 'Video not found'], 404);
    }

    $dataUrl = $request->input('thumbnail');
    if ($dataUrl && preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/', $dataUrl, $matches)) {
        $imageContent = base64_decode($matches[2]);
        if (strlen($imageContent) > 100) {
            $thumbnailRelPath = 'thumbnails/' . pathinfo($video->storage_path, PATHINFO_FILENAME) . '.jpg';
            \Illuminate\Support\Facades\Storage::disk($video->disk ?? 'public')->put($thumbnailRelPath, $imageContent);
            $video->thumbnail_path = $thumbnailRelPath;
        }
    }

    $duration = (int) $request->input('duration');
    if ($duration > 0 && $video->duration_seconds <= 0) {
        $video->duration_seconds = $duration;
    }

    $width = (int) $request->input('width');
    $height = (int) $request->input('height');
    if ($width > 0 && $height > 0 && ($video->width <= 0 || $video->height <= 0)) {
        $video->width = $width;
        $video->height = $height;
        $video->aspect_ratio = app(\App\Services\Media\VideoProcessingService::class)->calculateAspectRatio($width, $height);
    }

    $video->save();

    return response()->json([
        'success' => true,
        'id' => $video->id,
        'thumbnail_path' => $video->thumbnail_path,
        'thumbnail_url' => asset('storage/' . ltrim($video->thumbnail_path, '/')),
        'duration' => $video->duration_seconds,
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/oauth/redirect/{platform}', [SocialOAuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('/oauth/callback/{platform}', [SocialOAuthController::class, 'callback'])->name('oauth.callback');
});

Route::match(['get', 'post'], '/webhooks/social/{platform}', [SocialWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])
    ->name('webhooks.social');

Route::match(['get', 'post'], '/webhooks/deploy', [DeployWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])
    ->name('webhooks.deploy');

Route::match(['get', 'post'], '/admin/deploy', [DeployWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])
    ->name('admin.deploy');

// Public storage file streaming fallback (ensures videos & thumbnails play even if symlink is missing)
Route::get('/storage/{path}', function (string $path) {
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    if (! $disk->exists($path)) {
        abort(404);
    }
    return response()->file($disk->path($path), [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Cache-Control' => 'public, max-age=604800',
    ]);
})->where('path', '.*')->name('storage.file');

// Background automation heartbeat & web-cron endpoint (Optimized & Throttled)
Route::match(['get', 'post'], '/cron/process', function () {
    // Throttle: Don't execute more than once every 90 seconds across all tabs/requests
    $cacheKey = 'cron_process_last_run';
    if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
        return response()->json([
            'status' => 'skipped',
            'message' => 'Throttled (already executed recently)',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    \Illuminate\Support\Facades\Cache::put($cacheKey, now()->timestamp, 90);

    $orchestrator = app(\App\Services\Automation\AutomationOrchestratorService::class);
    $schedulingService = app(\App\Services\Publisher\SchedulingService::class);

    $schedulesCount = $orchestrator->checkAndRunDueSchedules();
    $dispatchedCount = $schedulingService->dispatchDuePosts();

    $liveWatchdogStats = [];
    try {
        $liveWatchdogStats = app(\App\Services\LiveStream\LiveStreamService::class)->checkHealthAndWatchdog();
    } catch (\Throwable) {}

    // Process at most 1 queued job with a strict 2-second timeout to avoid locking the web worker
    if ($dispatchedCount > 0) {
        try {
            \Illuminate\Support\Facades\Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-jobs' => 1,
                '--max-time' => 2,
                '--tries' => 1,
            ]);
        } catch (\Throwable) {}
    }

    return response()->json([
        'status' => 'ok',
        'schedules_executed' => $schedulesCount,
        'posts_dispatched' => $dispatchedCount,
        'timestamp' => now()->toIso8601String(),
    ]);
})->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])->name('cron.process');
