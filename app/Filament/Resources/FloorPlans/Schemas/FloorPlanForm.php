<?php

namespace App\Filament\Resources\FloorPlans\Schemas;

use Filament\Schemas\Schema;

class FloorPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('branch_id')->relationship('branch', 'name')->searchable()->preload()->required(),
                \Filament\Forms\Components\TextInput::make('name')->required(),
                \Filament\Forms\Components\Toggle::make('is_active')->required()
            ]);
    }
}
