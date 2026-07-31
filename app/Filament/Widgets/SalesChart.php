<?php

namespace App\Filament\Widgets;

use App\Support\DashboardFilters;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class SalesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Sales Chart';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $until = filled($this->pageFilters['until'] ?? null)
            ? Carbon::parse($this->pageFilters['until'])
            : now();

        $from = filled($this->pageFilters['from'] ?? null)
            ? Carbon::parse($this->pageFilters['from'])
            : $until->copy()->subDays(6);

        // One query for the whole range against the pre-aggregated
        // invoice_daily_summary view, instead of one Invoice::sum() query per
        // day (7+ queries) against the raw invoices table.
        $revenueByDate = DashboardFilters::scopeDailySummary($this->pageFilters)
            ->where('sale_date', '>=', $from->toDateString())
            ->where('sale_date', '<=', $until->toDateString())
            ->selectRaw('sale_date, SUM(revenue) as revenue')
            ->groupBy('sale_date')
            ->pluck('revenue', 'sale_date');

        $data = [];
        $labels = [];

        foreach (CarbonPeriod::create($from, $until) as $date) {
            $labels[] = $date->format('M d');
            $data[] = (float) ($revenueByDate[$date->toDateString()] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data,
                    'fill' => 'start',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
