<?php

namespace App\Filament\Resources\SerialNumbers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SerialNumberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')->relationship('product', 'name')->searchable()->preload()->required(),
                Select::make('product_variant_id')->relationship('productVariant', 'name')->searchable()->preload()->required(),
                Select::make('branch_id')->relationship('branch', 'name')->searchable()->preload()->required(),
                TextInput::make('serial_number')->required(),
                Select::make('status')
                    ->options(['in_stock' => 'In stock', 'sold' => 'Sold'])
                    ->searchable()
                    ->required(),
                TextInput::make('warranty_end_date')->required(),
            ]);
    }
}
