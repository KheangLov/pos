<?php

namespace App\Filament\Resources\FloorPlans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FloorPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')->relationship('branch', 'name', fn ($query) => $query->where('company_id', auth()->user()->company_id))->searchable()->preload()->required(),
                TextInput::make('name')->required(),
                Toggle::make('is_active')->required(),
            ]);
    }
}
