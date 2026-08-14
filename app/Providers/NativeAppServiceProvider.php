<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // Thin-wrapper mode (see .loop/progress.md, goal 2): Omni POS is
        // server-first — Postgres/Redis/ES/MinIO/Reverb run in compose, so the
        // desktop app is a kiosk-style shell pointing at the deployed/LAN URL
        // (NATIVE_APP_URL), not a bundled local server.
        //
        // NOTE: the framework still boots inside the wrapper, so the machine
        // running it must be able to reach the app's DB/Redis (this host, with
        // the compose stack and forwarded ports, qualifies).
        Window::open('pos')
            ->title(config('app.name', 'Omni POS'))
            ->url(env('NATIVE_APP_URL', 'https://192.168.31.88'))
            ->kiosk(filter_var(env('NATIVE_KIOSK', false), FILTER_VALIDATE_BOOLEAN))
            ->preventLeaveDomain()
            ->resizable()
            ->focusable(true);
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
