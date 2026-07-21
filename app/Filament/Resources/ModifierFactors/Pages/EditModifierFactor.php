<?php

namespace App\Filament\Resources\ModifierFactors\Pages;

use App\Filament\Resources\ModifierFactors\ModifierFactorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModifierFactor extends EditRecord
{
    protected static string $resource = ModifierFactorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
