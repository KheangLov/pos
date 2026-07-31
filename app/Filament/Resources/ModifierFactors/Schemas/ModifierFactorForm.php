<?php

namespace App\Filament\Resources\ModifierFactors\Schemas;

use Filament\Schemas\Schema;

class ModifierFactorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('company_id')->relationship('company', 'name')->searchable()->preload()->required(),
                \Filament\Forms\Components\TextInput::make('name')->required(),
                \Filament\Forms\Components\TextInput::make('multiplier')->required(),
                \Filament\Forms\Components\Toggle::make('is_active')->required()
            ]);
    }
}
