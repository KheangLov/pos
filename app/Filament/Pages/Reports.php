<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\ProductSalesDaily;
use App\Support\TableFilters;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static \UnitEnum|string|null $navigationGroup = 'Operations';

    protected string $view = 'filament.pages.reports';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->reportQuery())
            ->defaultSort('total_revenue', 'desc')
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('product_name')->label('Product')->sortable()->searchable(),
                TextColumn::make('total_quantity')->label('Units Sold')->sortable(),
                TextColumn::make('total_revenue')->label('Total Revenue')->money()->sortable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => Branch::query()->where('company_id', auth()->user()->company_id)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $branchId) => $q->where('product_sales_daily.branch_id', $branchId),
                    )),
                TableFilters::dateRange('product_sales_daily.sale_date', 'Order date'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshNow')
                ->label('Refresh now')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    Artisan::call('app:refresh-reporting-views');

                    Notification::make()
                        ->title('Report refreshed')
                        ->body('Figures now reflect all sales up to this moment.')
                        ->success()
                        ->send();
                }),
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->exportCsv()),
            Action::make('exportXlsx')
                ->label('Export XLSX')
                ->icon('heroicon-o-table-cells')
                ->action(fn () => $this->exportXlsx()),
        ];
    }

    /**
     * Reads product_sales_daily — a materialized view pre-aggregated to
     * (product, branch, day) — instead of joining and grouping the raw
     * order_items/invoices tables on every page load. See
     * database/migrations/*_create_reporting_materialized_views.php for how
     * it's built and routes/console.php for how it's kept fresh; the
     * "Refresh now" action above covers the gap between scheduled refreshes.
     *
     * Scoped to the logged-in user's company through branch_id — the raw
     * join version of this query had no company scoping at all.
     */
    private function reportQuery(): Builder
    {
        return ProductSalesDaily::query()
            ->join('products', 'products.id', '=', 'product_sales_daily.product_id')
            ->whereIn('product_sales_daily.branch_id', Branch::query()->where('company_id', auth()->user()->company_id)->pluck('id'))
            ->select(
                'products.id as id',
                'products.name as product_name',
                DB::raw('SUM(product_sales_daily.quantity) as total_quantity'),
                DB::raw('SUM(product_sales_daily.revenue) as total_revenue')
            )
            ->groupBy('products.id', 'products.name');
    }

    /**
     * Exports run outside Filament's own table-rendering pipeline, so unlike
     * the live table (which applies the registered ->filters() automatically)
     * this re-applies whatever filter values are currently set in the UI by
     * hand, so the downloaded file always matches what's on screen.
     */
    private function filteredReportQuery(): Builder
    {
        $query = $this->reportQuery();
        $filters = $this->tableFilters ?? [];

        if ($branchId = $filters['branch_id']['value'] ?? null) {
            $query->where('product_sales_daily.branch_id', $branchId);
        }

        // Filament nests a dotted filter name ("product_sales_daily.sale_date")
        // into a nested tableFilters array rather than keeping the dot literal,
        // so the state lives at ['product_sales_daily']['sale_date'], not a
        // flat key.
        if ($from = $filters['product_sales_daily']['sale_date']['from'] ?? null) {
            $query->where('product_sales_daily.sale_date', '>=', $from);
        }

        if ($until = $filters['product_sales_daily']['sale_date']['until'] ?? null) {
            $query->where('product_sales_daily.sale_date', '<=', $until);
        }

        return $query;
    }

    private function exportCsv(): StreamedResponse
    {
        $rows = $this->filteredReportQuery()->orderByDesc('total_revenue')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Product', 'Units Sold', 'Total Revenue'], escape: '');

            foreach ($rows as $row) {
                fputcsv($out, [$row->product_name, $row->total_quantity, number_format((float) $row->total_revenue, 2, '.', '')], escape: '');
            }

            fclose($out);
        }, 'sales-report-'.now()->format('Y-m-d').'.csv');
    }

    private function exportXlsx(): StreamedResponse
    {
        $rows = $this->filteredReportQuery()->orderByDesc('total_revenue')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales Report');
        $sheet->fromArray(['Product', 'Units Sold', 'Total Revenue'], null, 'A1');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        $row = 2;
        foreach ($rows as $r) {
            $sheet->setCellValue("A{$row}", $r->product_name);
            $sheet->setCellValue("B{$row}", (int) $r->total_quantity);
            $sheet->setCellValue("C{$row}", round((float) $r->total_revenue, 2));
            $row++;
        }

        foreach (['A', 'B', 'C'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'sales-report-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
