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

        // SQLite Eşzamanlı Kilitlenme Önleyici (WAL Modu & 60s Busy Timeout)
        try {
            if (config('database.default') === 'sqlite') {
                \Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 60000;');
                \Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode = WAL;');
                \Illuminate\Support\Facades\DB::statement('PRAGMA synchronous = NORMAL;');
            }
        } catch (\Throwable) {}

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('schedules')) {
                if (! \Illuminate\Support\Facades\Schema::hasColumn('schedules', 'post_type')) {
                    try {
                        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                    } catch (\Throwable) {}

                    if (! \Illuminate\Support\Facades\Schema::hasColumn('schedules', 'post_type')) {
                        \Illuminate\Support\Facades\Schema::table('schedules', function ($table) {
                            $table->string('post_type', 32)->default('video')->nullable();
                        });
                    }
                }

                if (! \Illuminate\Support\Facades\Schema::hasColumn('scheduled_posts', 'post_type')) {
                    \Illuminate\Support\Facades\Schema::table('scheduled_posts', function ($table) {
                        $table->string('post_type', 32)->default('video')->nullable();
                    });
                }

                if (! \Illuminate\Support\Facades\Schema::hasColumn('scheduled_post_targets', 'post_type')) {
                    \Illuminate\Support\Facades\Schema::table('scheduled_post_targets', function ($table) {
                        $table->string('post_type', 32)->default('video')->nullable();
                    });
                }
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
