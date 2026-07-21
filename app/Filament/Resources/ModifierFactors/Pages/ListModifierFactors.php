<?php

namespace App\Filament\Resources\ModifierFactors\Pages;

use App\Filament\Resources\ModifierFactors\ModifierFactorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModifierFactors extends ListRecords
{
    protected static string $resource = ModifierFactorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
