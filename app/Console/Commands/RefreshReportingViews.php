<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshReportingViews extends Command
{
    protected $signature = 'app:refresh-reporting-views';

    protected $description = 'Refresh the materialized views behind Reports and the dashboard';

    public function handle(): int
    {
        $started = microtime(true);

        // CONCURRENTLY: rebuilds into a shadow copy and swaps it in, so
        // anyone currently reading the view (someone on the Reports page or
        // dashboard right now) is never blocked and never sees a half-refreshed
        // result. Requires the unique indexes created alongside the views.
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY product_sales_daily');
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY invoice_daily_summary');

        $ms = (int) ((microtime(true) - $started) * 1000);
        $this->components->info("Reporting views refreshed in {$ms}ms.");

        return self::SUCCESS;
    }
}
