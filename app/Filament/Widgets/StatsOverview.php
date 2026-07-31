<?php

namespace App\Filament\Widgets;

use App\Support\DashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $hasDateFilter = filled($this->pageFilters['from'] ?? null) || filled($this->pageFilters['until'] ?? null);

        $query = DashboardFilters::scopeDailySummary($this->pageFilters);

        if (! $hasDateFilter) {
            $query->where('sale_date', today());
        }

        $row = $query->selectRaw('COALESCE(SUM(revenue), 0) as revenue, COALESCE(SUM(order_count), 0) as orders')->first();
        $revenue = (float) $row->revenue;
        $orders = (int) $row->orders;
        $avg = $orders > 0 ? $revenue / $orders : 0;

        return [
            Stat::make($hasDateFilter ? 'Revenue' : 'Revenue Today', '$'.number_format($revenue, 2))
                ->description($hasDateFilter ? 'Total sales in range' : 'Total sales today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Total Orders', $orders)
                ->description($hasDateFilter ? 'Orders placed in range' : 'Orders placed today')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),
            Stat::make('Average Order Value', '$'.number_format($avg, 2))
                ->description('Average per order')
                ->color('warning'),
        ];
    }
}
