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

Route::match(['get', 'post'], '/admin/quick-fix', function () {
    $service = app(\App\Services\Media\VideoProcessingService::class);
    $videos = \App\Models\Video::all();
    $count = 0;
    $errors = [];

    // Ensure storage link
    if (! file_exists(public_path('storage'))) {
        @\Illuminate\Support\Facades\Artisan::call('storage:link');
    }

    foreach ($videos as $video) {
        try {
            if ($video->storage_path && \Illuminate\Support\Facades\Storage::disk($video->disk ?? 'public')->exists($video->storage_path)) {
                $metadata = $service->analyzeVideo($video->storage_path);
                $video->update($metadata->toArray());
                $count++;
            }
        } catch (\Throwable $e) {
            $errors[] = "Video #{$video->id}: " . $e->getMessage();
        }
    }

    $msg = "{$count} adet video başarıyla analiz edildi ve önizlemeleri oluşturuldu.";
    if (! empty($errors)) {
        $msg .= " Hatalar: " . implode(', ', array_slice($errors, 0, 3));
    }

    return redirect('/admin/videos')->with('status', $msg);
})->name('admin.quick-fix');

Route::get('/quick-fix', fn () => redirect('/admin/quick-fix'));

Route::get('/php-path', function () {
    $disabled = explode(',', (string) ini_get('disable_functions'));
    $disabled = array_map('trim', $disabled);

    $ffmpegChecks = [
        'ffmpeg',
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/usr/bin/avconv',
    ];

    $ffmpegFound = [];
    foreach ($ffmpegChecks as $f) {
        if (@file_exists($f)) {
            $ffmpegFound[] = $f;
        }
    }

    $shellTest = null;
    if (function_exists('shell_exec') && ! in_array('shell_exec', $disabled, true)) {
        $shellTest = @shell_exec('ffmpeg -version 2>&1') ?: 'shell_exec returned empty';
    } else {
        $shellTest = 'shell_exec is disabled or does not exist';
    }

    $sampleVideo = \App\Models\Video::first();
    $sampleInfo = null;
    if ($sampleVideo && $sampleVideo->storage_path) {
        $fullPath = \Illuminate\Support\Facades\Storage::disk($sampleVideo->disk ?? 'public')->path($sampleVideo->storage_path);
        $sampleInfo = [
            'id' => $sampleVideo->id,
            'title' => $sampleVideo->title,
            'storage_path' => $sampleVideo->storage_path,
            'exists' => file_exists($fullPath),
            'filesize' => file_exists($fullPath) ? filesize($fullPath) : 0,
            'thumbnail_path' => $sampleVideo->thumbnail_path,
            'thumbnail_exists' => $sampleVideo->thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->exists($sampleVideo->thumbnail_path) : false,
        ];
    }

    return response()->json([
        'current_php_version' => PHP_VERSION,
        'php_binary' => PHP_BINARY,
        'uname' => php_uname(),
        'arch' => php_uname('m'),
        'shell_test' => $shellTest,
        'ffmpeg_found' => $ffmpegFound,
        'disabled_functions' => $disabled,
        'sample_video' => $sampleInfo,
    ]);
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/oauth/redirect/{platform}', [SocialOAuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('/oauth/callback/{platform}', [SocialOAuthController::class, 'callback'])->name('oauth.callback');
});

Route::match(['get', 'post'], '/webhooks/social/{platform}', [SocialWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])
    ->name('webhooks.social');

Route::post('/webhooks/deploy', [DeployWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])
    ->name('webhooks.deploy');

// Public storage file streaming fallback (ensures videos & thumbnails play even if symlink is missing)
Route::get('/storage/{path}', function (string $path) {
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    if (! $disk->exists($path)) {
        abort(404);
    }
    return response()->file($disk->path($path));
})->where('path', '.*')->name('storage.file');
