<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    // TelescopeServiceProvider is registered conditionally in AppServiceProvider:
    // Telescope is a dev dependency, so listing it here would fatal any build
    // installed with `composer install --no-dev`.
];
