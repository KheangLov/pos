<?php

namespace App\Filament\Resources\ProductVariants\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductVariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')->relationship('product', 'name', fn ($query) => $query->where('company_id', auth()->user()->company_id))->searchable()->preload()->required(),
                TextInput::make('name')->required(),
                TextInput::make('additional_price')->numeric()->prefix('$'),
                TextInput::make('sku')->required(),
                TextInput::make('barcode')->required(),
                Toggle::make('is_active')->required(),
            ]);
    }
}
