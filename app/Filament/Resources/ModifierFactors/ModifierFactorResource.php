<?php

namespace App\Filament\Resources\ModifierFactors;

use App\Filament\Resources\ModifierFactors\Pages\CreateModifierFactor;
use App\Filament\Resources\ModifierFactors\Pages\EditModifierFactor;
use App\Filament\Resources\ModifierFactors\Pages\ListModifierFactors;
use App\Filament\Resources\ModifierFactors\Schemas\ModifierFactorForm;
use App\Filament\Resources\ModifierFactors\Tables\ModifierFactorsTable;
use App\Models\ModifierFactor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ModifierFactorResource extends Resource
{
    protected static ?string $model = ModifierFactor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ModifierFactorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModifierFactorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModifierFactors::route('/'),
            'create' => CreateModifierFactor::route('/create'),
            'edit' => EditModifierFactor::route('/{record}/edit'),
        ];
    }
}
