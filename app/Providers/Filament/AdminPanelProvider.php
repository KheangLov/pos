<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnforceLicense;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->sidebarCollapsibleOnDesktop()
            ->spa()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->maxContentWidth(Width::Full)
            ->brandName(fn () => auth()->user()?->company?->name ?? 'Omni POS')
            // Falls back to the same placeholder artwork used everywhere else
            // a company logo can be missing (see product/company ImageColumns
            // and the eMenu header) — without it, a company with no logo
            // uploaded showed text-only branding in the navbar.
            ->brandLogo(fn () => auth()->user()?->company?->logoUrl() ?? asset('images/company-placeholder.svg'))
            ->brandLogoHeight('2rem')
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            // The custom Dashboard page (app/Filament/Pages/Dashboard.php) is
            // picked up by this discovery pass — it doesn't need registering
            // via ->pages(), same as Kds/Pos/Reports/SystemLogs below it.
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            // AccountWidget is replaced by WelcomeWidget (a full-width
            // subclass) and FilamentInfoWidget is dropped entirely — both
            // live in app/Filament/Widgets and are picked up by discovery.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): View => view('license.banner'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Persistent, so the check also runs on Livewire update requests.
            // Without that, a POS page already open in a browser would keep
            // taking orders through Livewire after the licence was blocked —
            // only a full page load would ever hit the gate.
            ->middleware([EnforceLicense::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
