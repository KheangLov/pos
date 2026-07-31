<?php

namespace App\Filament\Resources\ModifierGroups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ModifierGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')->relationship('company', 'name')->searchable()->preload()->required(),
                TextInput::make('name')->required(),
                Select::make('selection_type')
                    ->options(['single' => 'Single', 'multiple' => 'Multiple'])
                    ->searchable()
                    ->required(),
                TextInput::make('min_selections')->numeric(),
                TextInput::make('max_selections')->numeric(),
                Toggle::make('is_active')->required(),
            ]);
    }
}
