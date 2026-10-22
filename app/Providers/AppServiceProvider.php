<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\AI\Contracts\AiContentProviderInterface::class,
            \App\Services\AI\OpenAiContentService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '600');

        config([
            'livewire.temporary_file_upload.disk' => 'public',
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:512000'],
            'livewire.temporary_file_upload.directory' => 'livewire-tmp',
            'livewire.temporary_file_upload.max_upload_time' => 30,
            'livewire.temporary_file_upload.middleware' => 'throttle:300,1',
        ]);
    }
}
