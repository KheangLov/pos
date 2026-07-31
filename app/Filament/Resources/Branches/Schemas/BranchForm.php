<?php

namespace App\Filament\Resources\Branches\Schemas;

use Filament\Schemas\Schema;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('company_id')->relationship('company', 'name')->searchable()->preload()->required(),
                \Filament\Forms\Components\TextInput::make('name')->required(),
                \Filament\Forms\Components\TextInput::make('email')->email()->required(),
                \Filament\Forms\Components\TextInput::make('phone')->required(),
                \Filament\Forms\Components\TextInput::make('address')->required(),
                \Filament\Forms\Components\Toggle::make('is_active')->required()
            ]);
    }
}
