<?php

namespace App\Filament\Resources\Tables\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('floor_plan_id')->relationship('floorPlan', 'name', fn ($query) => $query->whereHas('branch', fn ($q) => $q->where('company_id', auth()->user()->company_id)))->searchable()->preload()->required(),
                TextInput::make('name')->required(),
                TextInput::make('capacity')->numeric(),
                Select::make('shape')
                    ->options(['round' => 'Round', 'rectangle' => 'Rectangle'])
                    ->searchable()
                    ->required(),
                TextInput::make('position_x')->required(),
                TextInput::make('position_y')->required(),
                TextInput::make('width')->required(),
                TextInput::make('height')->required(),
                Select::make('status')
                    ->options(['available' => 'Available', 'occupied' => 'Occupied'])
                    ->searchable()
                    ->required(),
                TextInput::make('uuid')->required(),
            ]);
    }
}
