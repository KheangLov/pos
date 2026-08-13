<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
                TextInput::make('password')->password()->required(fn (string $operation): bool => $operation === 'create')->dehydrated(fn (?string $state) => filled($state)),
                Hidden::make('company_id')->default(fn () => auth()->user()->company_id),
                Select::make('branch_id')->relationship('branch', 'name', fn ($query) => $query->where('company_id', auth()->user()->company_id))->searchable()->preload()->required(),
            ]);
    }
}
