<?php

$providers = [
    App\Providers\AppServiceProvider::class,
];

if (class_exists(\Filament\PanelProvider::class)) {
    $providers[] = App\Providers\Filament\AdminPanelProvider::class;
}

return $providers;
