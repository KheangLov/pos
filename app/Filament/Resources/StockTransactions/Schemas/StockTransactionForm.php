<?php

namespace App\Filament\Resources\StockTransactions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StockTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')->relationship('branch', 'name')->searchable()->preload()->required(),
                Select::make('product_id')->relationship('product', 'name')->searchable()->preload()->required(),
                Select::make('product_variant_id')->relationship('productVariant', 'name')->searchable()->preload()->required(),
                TextInput::make('quantity')->numeric()->required(),
                Select::make('type')
                    ->options(['sale' => 'Sale', 'purchase' => 'Purchase'])
                    ->searchable()
                    ->required(),
                // reference_type/reference_id point at whatever generated this
                // transaction (an Invoice, from the POS/eMenu checkout flow) via
                // a polymorphic relation — there's no single model a Select can
                // target, and in practice these are only ever set by the system,
                // never entered by hand here, so they stay plain and optional.
                TextInput::make('reference_type')
                    ->helperText('Set automatically when a sale is recorded from a checkout. Leave blank for manual adjustments.'),
                TextInput::make('reference_id')->numeric(),
                Textarea::make('notes')->columnSpanFull(),
            ]);
    }
}
