<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('name')->required(),
                \Filament\Forms\Components\TextInput::make('email')->email()->required(),
                \Filament\Forms\Components\TextInput::make('password')->password()->required(fn (string $operation): bool => $operation === 'create')->dehydrated(fn (?string $state) => filled($state)),
                \Filament\Forms\Components\Select::make('company_id')->relationship('company', 'name')->searchable()->preload()->required(),
                \Filament\Forms\Components\Select::make('branch_id')->relationship('branch', 'name')->searchable()->preload()->required()
            ]);
    }
}
