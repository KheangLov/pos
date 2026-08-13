<?php

namespace App\Filament\Resources\StockTransactions\Schemas;

use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;

class StockTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')->relationship('branch', 'name', fn ($query) => $query->where('company_id', auth()->user()->company_id))->searchable()->preload()->required(),
                TextInput::make('barcode_scan')
                    ->label('Scan barcode')
                    ->live(onBlur: true)
                    ->dehydrated(false)
                    ->helperText('Looks up the product (and variant, if the barcode matches one) below.')
                    ->suffixAction(
                        Action::make('scanBarcode')
                            ->icon('heroicon-o-camera')
                            ->extraAttributes(['onclick' => "window.BarcodeScanner.open(code => \$wire.set('data.barcode_scan', code))"]),
                    )
                    ->afterStateUpdated(function (?string $state, callable $set) {
                        if (! $state) {
                            return;
                        }

                        $companyId = auth()->user()->company_id;

                        $product = Product::where('company_id', $companyId)->where('barcode', $state)->first();
                        $variant = $product ? null : ProductVariant::where('barcode', $state)
                            ->whereHas('product', fn ($query) => $query->where('company_id', $companyId))
                            ->first();

                        if ($product) {
                            $set('product_id', $product->id);
                        } elseif ($variant) {
                            $set('product_id', $variant->product_id);
                            $set('product_variant_id', $variant->id);
                        } else {
                            Notification::make()->title('No product found for that barcode')->warning()->send();
                        }
                    }),
                Select::make('product_id')->relationship('product', 'name', fn ($query) => $query->where('company_id', auth()->user()->company_id))->searchable()->preload()->required(),
                Select::make('product_variant_id')->relationship('productVariant', 'name', fn ($query) => $query->whereHas('product', fn ($q) => $q->where('company_id', auth()->user()->company_id)))->searchable()->preload()->required(),
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
