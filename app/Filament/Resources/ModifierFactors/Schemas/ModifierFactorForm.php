<?php

namespace App\Filament\Resources\ModifierFactors\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ModifierFactorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('company_id')->default(fn () => auth()->user()->company_id),
                TextInput::make('name')->required(),
                TextInput::make('multiplier')->required(),
                Toggle::make('is_active')->required(),
            ]);
    }
}
