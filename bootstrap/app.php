<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Stale cache breaker: Ensure Git deployments don't get stuck on old cached routes or config
foreach (['routes-v7.php', 'config.php'] as $cacheFile) {
    $cacheFilePath = __DIR__ . '/cache/' . $cacheFile;
    if (file_exists($cacheFilePath)) {
        @unlink($cacheFilePath);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
