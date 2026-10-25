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

        $appUrl = config('app.url');
        if (empty($appUrl) || str_contains($appUrl, 'localhost')) {
            config(['filesystems.disks.public.url' => '/storage']);
        }

        if (! file_exists(public_path('storage'))) {
            @\Illuminate\Support\Facades\Artisan::call('storage:link');
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('schedules') && ! \Illuminate\Support\Facades\Schema::hasColumn('schedules', 'schedule_type')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable) {
            // Sessizce geç
        }

        \Filament\Tables\Table::configureUsing(function (\Filament\Tables\Table $table): void {
            $table->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible);
        });

        // Denetim Günlüğü (Audit Log) Gözlemcileri ve Olayları
        \App\Models\Video::observe(\App\Observers\VideoObserver::class);
        \App\Models\ScheduledPost::observe(\App\Observers\ScheduledPostObserver::class);
        \App\Models\SocialAccount::observe(\App\Observers\SocialAccountObserver::class);

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            function (\Illuminate\Auth\Events\Login $event) {
                if ($event->user) {
                    \App\Models\AuditLog::record('admin_login', $event->user, [
                        'email' => $event->user->email,
                        'name' => $event->user->name,
                        'guard' => $event->guard ?? 'web',
                    ]);
                }
            }
        );
    }
}
