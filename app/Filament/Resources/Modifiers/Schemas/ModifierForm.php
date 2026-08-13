<?php

namespace App\Filament\Resources\Modifiers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ModifierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('modifier_group_id')->relationship('modifierGroup', 'name', fn ($query) => $query->where('company_id', auth()->user()->company_id))->searchable()->preload()->required(),
                TextInput::make('name')->required(),
                TextInput::make('price')->numeric()->prefix('$'),
                Select::make('modifier_factor_id')
                    ->label('Factor')
                    ->relationship('modifierFactor', 'name', fn ($query) => $query->where('company_id', auth()->user()->company_id))
                    ->searchable()
                    ->preload()
                    ->helperText('Optional. Scales the price above by the factor\'s multiplier — e.g. a $0.50 modifier with a "Double" (×2) factor charges $1.00.'),
                Toggle::make('is_active')->required(),
            ]);
    }
}
