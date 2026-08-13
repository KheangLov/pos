<?php

namespace App\Filament\Resources\FloorPlans;

use App\Filament\Resources\FloorPlans\Pages\CreateFloorPlan;
use App\Filament\Resources\FloorPlans\Pages\EditFloorPlan;
use App\Filament\Resources\FloorPlans\Pages\ListFloorPlans;
use App\Filament\Resources\FloorPlans\Schemas\FloorPlanForm;
use App\Filament\Resources\FloorPlans\Tables\FloorPlansTable;
use App\Models\FloorPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FloorPlanResource extends Resource
{
    protected static ?string $model = FloorPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static \UnitEnum|string|null $navigationGroup = 'Establishment';

    /**
     * FloorPlan has no company_id of its own — scoped through branch.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('branch', fn (Builder $query) => $query->where('company_id', auth()->user()->company_id));
    }

    public static function form(Schema $schema): Schema
    {
        return FloorPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FloorPlansTable::configure($table);
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
            'index' => ListFloorPlans::route('/'),
            'create' => CreateFloorPlan::route('/create'),
            'edit' => EditFloorPlan::route('/{record}/edit'),
        ];
    }
}
