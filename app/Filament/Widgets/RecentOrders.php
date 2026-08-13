<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Support\DashboardFilters;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

class RecentOrders extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DashboardFilters::scopeInvoices(Invoice::query()->with('table'), $this->pageFilters)->latest()->limit(5))
            ->columns([
                TextColumn::make('id')->label('Order ID')->sortable(),
                TextColumn::make('table.name')->label('Table')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid', 'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'secondary',
                    }),
                TextColumn::make('total')->money()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->paginated(false);
    }
}
