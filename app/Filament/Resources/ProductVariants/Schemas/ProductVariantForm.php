<?php

namespace App\Filament\Resources\ProductVariants\Schemas;

use Filament\Schemas\Schema;

class ProductVariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('product_id')->relationship('product', 'name')->searchable()->preload()->required(),
                \Filament\Forms\Components\TextInput::make('name')->required(),
                \Filament\Forms\Components\TextInput::make('additional_price')->numeric()->prefix('$'),
                \Filament\Forms\Components\TextInput::make('sku')->required(),
                \Filament\Forms\Components\TextInput::make('barcode')->required(),
                \Filament\Forms\Components\Toggle::make('is_active')->required()
            ]);
    }
}
