<?php

namespace App\Filament\Resources\FloorPlans\Pages;

use App\Filament\Resources\FloorPlans\FloorPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFloorPlan extends EditRecord
{
    protected static string $resource = FloorPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
