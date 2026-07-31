<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two materialized views back Reports.php and the dashboard widgets, so
 * neither has to re-aggregate order_items/invoices from scratch on every
 * page load. Both are kept fresh by a scheduled refresh (see
 * app:refresh-reporting-views, routes/console.php) rather than a database
 * trigger, so a sale is reflected within minutes, not instantly — acceptable
 * for a sales report and a dashboard summary, unlike KDS/order-tracking
 * (which stay on live Reverb broadcasts, not touched here).
 *
 * Grain is (branch_id, sale_date) for the invoice-level view and additionally
 * product_id for the line-item view — coarse enough to collapse the raw
 * tables into a handful of rows per branch per day, fine enough that Reports'
 * branch/date filters and the dashboard's branch/date filters both still just
 * SUM() the already-aggregated rows instead of touching order_items/invoices
 * directly.
 *
 * Only 'paid'/'completed' invoices are included — same terminal-status
 * convention already used for revenue elsewhere in this app (see
 * DashboardFilters, RecentOrders, InvoicesTable) — 'draft'/'pending'/
 * 'cancelled' invoices are not real revenue.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW product_sales_daily AS
            SELECT
                oi.product_id,
                i.branch_id,
                DATE(i.created_at) AS sale_date,
                SUM(oi.quantity) AS quantity,
                SUM(oi.subtotal) AS revenue
            FROM order_items oi
            JOIN invoices i ON i.id = oi.invoice_id
            WHERE i.status IN ('paid', 'completed')
            GROUP BY oi.product_id, i.branch_id, DATE(i.created_at)
            WITH DATA
        SQL);

        // Required for REFRESH MATERIALIZED VIEW CONCURRENTLY, which is what
        // lets the scheduled refresh run without locking out anyone currently
        // viewing the Reports page or dashboard.
        DB::statement('CREATE UNIQUE INDEX product_sales_daily_uk ON product_sales_daily (product_id, branch_id, sale_date)');

        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW invoice_daily_summary AS
            SELECT
                i.branch_id,
                DATE(i.created_at) AS sale_date,
                COUNT(*) AS order_count,
                SUM(i.total) AS revenue
            FROM invoices i
            WHERE i.status IN ('paid', 'completed')
            GROUP BY i.branch_id, DATE(i.created_at)
            WITH DATA
        SQL);

        DB::statement('CREATE UNIQUE INDEX invoice_daily_summary_uk ON invoice_daily_summary (branch_id, sale_date)');
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS product_sales_daily');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS invoice_daily_summary');
    }
};
