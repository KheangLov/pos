<?php

namespace App\Providers;

use App\Services\LicenseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped, not singleton: Octane keeps singletons alive across requests,
        // which would hide a licence installed while the app is running.
        $this->app->scoped(LicenseManager::class);

        $this->registerTelescope();
    }

    /**
     * Telescope is a dev-only dependency and is absent from release builds, so
     * its provider can only be registered once we know the class is there.
     */
    private function registerTelescope(): void
    {
        if (! $this->app->environment('local')) {
            return;
        }

        // Referenced as a string, not an import: the class genuinely does not
        // exist in a --no-dev build, and an import here invites tooling to
        // "helpfully" treat it as a hard dependency.
        if (! class_exists('Laravel\Telescope\Telescope')) {
            return;
        }

        if (class_exists(TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Surface N+1 queries, silently discarded attributes and missing
        // attributes during development; no-op in production.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
