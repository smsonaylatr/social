<?php

use App\Http\Controllers\DeployWebhookController;
use App\Http\Controllers\SocialOAuthController;
use App\Http\Controllers\SocialWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/setup-system', function () {
    Artisan::call('migrate', ['--force' => true]);
    $migrateOutput = Artisan::output();

    Artisan::call('db:seed', ['--class' => 'InitialSystemSeeder', '--force' => true]);
    $seedOutput = Artisan::output();

    Artisan::call('storage:link');
    Artisan::call('optimize:clear');

    return response()->json([
        'status' => 'success',
        'message' => 'Database migration, seeding, storage link and cache clearing executed successfully!',
        'migrate' => $migrateOutput,
        'seed' => $seedOutput,
    ]);
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/oauth/redirect/{platform}', [SocialOAuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('/oauth/callback/{platform}', [SocialOAuthController::class, 'callback'])->name('oauth.callback');
});

Route::match(['get', 'post'], '/webhooks/social/{platform}', [SocialWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.social');

Route::post('/webhooks/deploy', [DeployWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.deploy');
