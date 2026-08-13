<?php

namespace App\Filament\Resources\Discounts\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DiscountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('company_id')->default(fn () => auth()->user()->company_id),
                TextInput::make('name')->required(),
                Select::make('type')
                    ->options(['percentage' => 'Percentage', 'fixed' => 'Fixed amount'])
                    ->searchable()
                    ->default('percentage')
                    ->live()
                    ->required(),
                TextInput::make('value')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->suffix(fn (Get $get) => $get('type') === 'fixed' ? '$' : '%')
                    ->maxValue(fn (Get $get) => $get('type') === 'percentage' ? 100 : null),
                Toggle::make('is_active')->default(true)->required(),
            ]);
    }
}
