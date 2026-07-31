<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Shared query scoping for the dashboard widgets (StatsOverview, SalesChart,
 * RecentOrders), so the branch/date filters and the company boundary are
 * defined once instead of copied into each widget.
 */
class DashboardFilters
{
    /**
     * Live Eloquent scoping — used only by RecentOrders, which shows actual
     * order records (status, table, cashier) that a pre-aggregated view
     * can't provide and that genuinely need to be current, not a couple of
     * minutes stale.
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function scopeInvoices(Builder $query, ?array $filters): Builder
    {
        return $query
            ->whereHas('branch', fn (Builder $q) => $q->where('company_id', auth()->user()->company_id))
            ->when($filters['branch_id'] ?? null, fn (Builder $q, $branchId) => $q->where('branch_id', $branchId))
            ->when($filters['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
    }

    /**
     * Reads the invoice_daily_summary materialized view (see
     * database/migrations/*_create_reporting_materialized_views.php) instead
     * of aggregating the invoices table live — used by StatsOverview and
     * SalesChart, neither of which needs sub-minute freshness. Kept current
     * by a scheduled refresh (routes/console.php); Reports.php's "Refresh
     * now" action refreshes this same view too.
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function scopeDailySummary(?array $filters): QueryBuilder
    {
        return DB::table('invoice_daily_summary')
            ->whereIn('branch_id', Branch::query()->where('company_id', auth()->user()->company_id)->pluck('id'))
            ->when($filters['branch_id'] ?? null, fn (QueryBuilder $q, $branchId) => $q->where('branch_id', $branchId))
            ->when($filters['from'] ?? null, fn (QueryBuilder $q, $date) => $q->where('sale_date', '>=', $date))
            ->when($filters['until'] ?? null, fn (QueryBuilder $q, $date) => $q->where('sale_date', '<=', $date));
    }
}
