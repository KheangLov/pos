<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Invoice;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('id')->label('Invoice #'),
                            TextEntry::make('branch.name')->label('Branch'),
                            TextEntry::make('table.name')->label('Table')->placeholder('Takeaway'),
                            TextEntry::make('user.name')->label('Cashier'),
                        ]),
                        Grid::make(4)->schema([
                            TextEntry::make('status')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => str($state)->headline())
                                ->color(fn (string $state): string => match ($state) {
                                    'pending', 'draft' => 'warning',
                                    'paid', 'completed' => 'success',
                                    'cancelled' => 'danger',
                                    default => 'gray',
                                }),
                            TextEntry::make('shift.id')->label('Shift #')->placeholder('—'),
                            TextEntry::make('created_at')->label('Placed')->dateTime(),
                            TextEntry::make('updated_at')->label('Last updated')->dateTime(),
                        ]),
                    ]),

                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('orderItems')
                            ->label(false)
                            ->schema([
                                Grid::make(6)->schema([
                                    TextEntry::make('product.name')
                                        ->label('Product')
                                        ->columnSpan(2)
                                        ->formatStateUsing(fn ($record) => $record->productVariant
                                            ? $record->product->name.' ('.$record->productVariant->name.')'
                                            : $record->product->name)
                                        ->helperText(fn ($record) => $record->modifiers->isNotEmpty()
                                            ? $record->modifiers->pluck('name')->implode(', ')
                                            : null),
                                    TextEntry::make('quantity')->label('Qty'),
                                    TextEntry::make('price')->label('Unit price')->money(),
                                    TextEntry::make('subtotal')->money(),
                                    TextEntry::make('status')
                                        ->badge()
                                        ->formatStateUsing(fn (string $state): string => str($state)->headline())
                                        ->color(fn (string $state): string => match ($state) {
                                            'pending' => 'warning',
                                            'preparing' => 'info',
                                            'ready' => 'primary',
                                            'completed' => 'success',
                                            default => 'gray',
                                        }),
                                ]),
                            ]),
                    ]),

                Section::make('Payments')
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->label(false)
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('method')->formatStateUsing(fn (string $state): string => str($state)->headline()),
                                    TextEntry::make('amount')->money(),
                                    TextEntry::make('status')
                                        ->badge()
                                        ->formatStateUsing(fn (string $state): string => str($state)->headline())
                                        ->color(fn (string $state): string => match ($state) {
                                            'successful' => 'success',
                                            'failed' => 'danger',
                                            default => 'gray',
                                        }),
                                    TextEntry::make('reference')->placeholder('—'),
                                ]),
                            ]),
                    ])
                    ->visible(fn (Invoice $record) => $record->payments->isNotEmpty()),

                Section::make('Totals')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('subtotal')->money(),
                            TextEntry::make('discount_total')->label('Discount')->money(),
                            TextEntry::make('tax_total')->label('Tax')->money(),
                            TextEntry::make('total')->money()->weight('bold'),
                        ]),
                    ]),
            ]);
    }
}
