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
    $candidates = [
        PHP_BINARY,
        str_replace(['sbin/php-fpm', 'sbin/php-fpm8.2', 'sbin/php-fpm8.3', 'sbin/php-fpm8.1'], 'bin/php', PHP_BINARY),
        '/usr/bin/php',
        '/usr/local/bin/php',
        '/opt/plesk/php/8.3/bin/php',
        '/opt/plesk/php/8.2/bin/php',
        '/opt/plesk/php/8.1/bin/php',
        '/opt/plesk/php/8.0/bin/php',
        '/usr/bin/plesk-php83',
        '/usr/bin/plesk-php82',
        '/usr/bin/plesk-php81',
    ];

    $existing = [];
    foreach ($candidates as $cmd) {
        if (!empty($cmd) && @file_exists($cmd)) {
            $existing[] = $cmd;
        }
    }

    if (@is_dir('/opt/plesk/php')) {
        foreach ((array) @glob('/opt/plesk/php/*/bin/php') as $found) {
            if (!empty($found) && @file_exists($found)) {
                $existing[] = $found;
            }
        }
    }

    $uniquePaths = array_values(array_unique($existing));
    $cliPath = !empty($uniquePaths) ? $uniquePaths[0] : 'php';

    return response()->json([
        'current_php_version' => PHP_VERSION,
        'php_binary' => PHP_BINARY,
        'available_cli_paths' => $uniquePaths,
        'plesk_deployment_script' => $cliPath . ' artisan optimize:clear' . PHP_EOL . $cliPath . ' artisan storage:link || true',
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
