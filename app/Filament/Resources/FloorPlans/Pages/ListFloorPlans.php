<?php

namespace App\Filament\Resources\FloorPlans\Pages;

use App\Filament\Resources\FloorPlans\FloorPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFloorPlans extends ListRecords
{
    protected static string $resource = FloorPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
