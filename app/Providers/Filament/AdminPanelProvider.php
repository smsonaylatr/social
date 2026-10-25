<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('SocialFlow AI')
            ->brandLogo(null)
            ->darkMode(true)
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('120s')
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Zinc,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="stylesheet" href="' . asset('css/custom-admin.css') . '?v=4.0">'
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn () => view('filament.components.header-deploy')
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn () => view('filament.components.auth-footer')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => '<script>(function(){try{var k="sf_last_cron_ts";var last=parseInt(localStorage.getItem(k)||"0",10);var now=Date.now();if(now-last>180000){localStorage.setItem(k,now);setTimeout(function(){fetch("/cron/process",{method:"POST",headers:{"X-Requested-With":"XMLHttpRequest"}}).catch(function(){});},4000);}}catch(e){}})();</script>'
            )
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Video Yönetimi')
                    ->icon('heroicon-o-video-camera'),
                NavigationGroup::make()
                    ->label('Sosyal Medya')
                    ->icon('heroicon-o-share'),
                NavigationGroup::make()
                    ->label('Yayın ve Otomasyon')
                    ->icon('heroicon-o-calendar-days'),
                NavigationGroup::make()
                    ->label('AI ve SEO Stüdyosu')
                    ->icon('heroicon-o-sparkles'),
                NavigationGroup::make()
                    ->label('Raporlar ve Günlükler')
                    ->icon('heroicon-o-chart-bar'),
                NavigationGroup::make()
                    ->label('Sistem Ayarları')
                    ->icon('heroicon-o-cog-6-tooth'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\StatsOverviewWidget::class,
                \App\Filament\Widgets\PlatformStatusOverviewWidget::class,
                \App\Filament\Widgets\UpcomingScheduledPostsWidget::class,
                \App\Filament\Widgets\SystemHealthOverviewWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
