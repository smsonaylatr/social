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

    // Clean old broken GD thumbnails from storage/app/public/thumbnails
    $thumbnailFiles = \Illuminate\Support\Facades\Storage::disk('public')->files('thumbnails');
    foreach ($thumbnailFiles as $file) {
        $size = \Illuminate\Support\Facades\Storage::disk('public')->size($file);
        if ($size < 25000) {
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
    if ($dataUrl && str_starts_with($dataUrl, 'data:image/jpeg;base64,')) {
        $base64 = substr($dataUrl, strlen('data:image/jpeg;base64,'));
        $imageContent = base64_decode($base64);

        $thumbnailRelPath = 'thumbnails/' . pathinfo($video->storage_path, PATHINFO_FILENAME) . '.jpg';
        \Illuminate\Support\Facades\Storage::disk($video->disk ?? 'public')->put($thumbnailRelPath, $imageContent);

        $video->thumbnail_path = $thumbnailRelPath;
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
