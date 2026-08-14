<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Telescope is off by default but records aggressively whenever it is switched
// on for debugging, and nothing ever cleaned up afterwards — that is how the
// entries table reached ~10 MB. Guarded by class_exists because Telescope is a
// dev dependency: an unconditional entry here would fail on every scheduler
// tick in a --no-dev release build.
if (class_exists('Laravel\Telescope\Telescope')) {
    Schedule::command('telescope:prune --hours=48')->daily();
}

// Keeps Reports and the dashboard's revenue/order-count figures reasonably
// current without re-aggregating order_items/invoices on every page load —
// see database/migrations/*_create_reporting_materialized_views.php. 2
// minutes was chosen to match how "live" already feels elsewhere in this app
// (the notification bell polls every 30s); a sale shows up in the report
// within a couple of minutes rather than instantly, which the Reports page's
// manual "Refresh now" action (see Reports.php) covers when that's not
// enough — e.g. checking a report immediately after closing a shift.
Schedule::command('app:refresh-reporting-views')->everyTwoMinutes();

// P1-5: poll Bakong for confirmed KHQR payments. No-op while no payment
// method carries a Bakong token (see app/Console/Commands/CheckKhqrPayments.php).
Schedule::command('khqr:check-pending')->everyMinute()->withoutOverlapping();
