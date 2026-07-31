<?php

namespace App\Filament\Resources\Shifts\Schemas;

use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shift;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class ShiftInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shift')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('branch.name')->label('Branch'),
                            TextEntry::make('user.name')->label('Cashier'),
                            TextEntry::make('opened_at')->dateTime(),
                            TextEntry::make('closed_at')->dateTime()->placeholder('Still open'),
                        ]),
                    ]),

                Section::make('Cash reconciliation')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('opening_amount')->label('Opening float')->money(),
                            TextEntry::make('cash_sales')
                                ->label('Cash sales')
                                ->state(fn (Shift $record) => static::cashSales($record))
                                ->money(),
                            TextEntry::make('expected_cash')
                                ->label('Expected in drawer')
                                ->state(fn (Shift $record) => (float) $record->opening_amount + static::cashSales($record))
                                ->money(),
                            TextEntry::make('closing_amount')
                                ->label('Counted at close')
                                ->money()
                                ->placeholder('—'),
                        ]),
                        TextEntry::make('variance')
                            ->label('Variance')
                            ->state(function (Shift $record) {
                                if ($record->status !== 'closed') {
                                    return null;
                                }

                                return (float) $record->closing_amount - ((float) $record->opening_amount + static::cashSales($record));
                            })
                            ->money()
                            ->placeholder('Shift still open')
                            ->color(function (Shift $record) {
                                if ($record->status !== 'closed') {
                                    return 'gray';
                                }
                                $variance = (float) $record->closing_amount - ((float) $record->opening_amount + static::cashSales($record));

                                return match (true) {
                                    abs($variance) < 0.01 => 'success',
                                    default => 'danger',
                                };
                            })
                            ->weight('bold'),
                    ]),

                Section::make('Sales summary')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('order_count')
                                ->label('Orders')
                                ->state(fn (Shift $record) => Invoice::where('shift_id', $record->id)->count()),
                            TextEntry::make('total_sales')
                                ->label('Total sales')
                                ->state(fn (Shift $record) => Invoice::where('shift_id', $record->id)->sum('total'))
                                ->money(),
                            TextEntry::make('card_sales')
                                ->label('Card sales')
                                ->state(fn (Shift $record) => static::paymentTotal($record, 'card'))
                                ->money(),
                            TextEntry::make('qr_sales')
                                ->label('QR sales')
                                ->state(fn (Shift $record) => static::paymentTotal($record, 'qr'))
                                ->money(),
                        ]),
                    ]),

                Section::make('Top products')
                    ->schema([
                        RepeatableEntry::make('topProducts')
                            ->label(false)
                            ->state(fn (Shift $record) => static::topProducts($record))
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('name')->label('Product'),
                                    TextEntry::make('quantity')->label('Units sold'),
                                    TextEntry::make('revenue')->label('Revenue')->money(),
                                ]),
                            ]),
                    ])
                    ->visible(fn (Shift $record) => static::topProducts($record) !== []),
            ]);
    }

    private static function cashSales(Shift $record): float
    {
        return (float) static::paymentTotal($record, 'cash');
    }

    private static function paymentTotal(Shift $record, string $method): float
    {
        return (float) Payment::where('method', $method)
            ->where('status', 'successful')
            ->whereHas('invoice', fn ($q) => $q->where('shift_id', $record->id))
            ->sum('amount');
    }

    /**
     * @return array<int, array{name: string, quantity: int, revenue: float}>
     */
    private static function topProducts(Shift $record): array
    {
        return OrderItem::query()
            ->join('invoices', 'order_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('invoices.shift_id', $record->id)
            ->select(
                'products.name as name',
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(order_items.subtotal) as revenue'),
            )
            ->groupBy('products.name')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'quantity' => (int) $row->quantity, 'revenue' => (float) $row->revenue])
            ->all();
    }
}
