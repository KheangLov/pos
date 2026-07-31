<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Require an authenticated administrator, in every environment.
     *
     * The parent implementation short-circuits with
     * `app()->environment('local') || Gate::check(...)`, which leaves
     * /telescope open to anyone who can reach the port — and the app publishes
     * 0.0.0.0:8000 because the eMenu is meant to be opened from phones on the
     * LAN. Overriding it drops that bypass so the gate below is always the
     * only way in.
     */
    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(fn ($request) => Gate::check('viewTelescope', [$request->user()]));
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     *
     * Deliberately not skipped on local: Telescope records request payloads and
     * response bodies verbatim, and a session cookie captured on a dev box is
     * just as usable as one captured anywhere else.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * Deliberately a dedicated permission rather than the Admin role: this is
     * a multi-tenant product, every customer company has its own Admin, and
     * Telescope shows all tenants' queries, payloads and responses at once.
     * `view_telescope` is granted to no role by default (RolePermissionSeeder
     * only syncs the generated per-entity permissions), so it has to be handed
     * to a specific operator on purpose.
     *
     * Nullable user: unauthenticated requests reach this too, and must fail
     * rather than error.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', fn (?User $user) => (bool) $user?->can('view_telescope'));
    }
}
