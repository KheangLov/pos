<?php

namespace App\Filament\Resources\SerialNumbers\Pages;

use App\Filament\Resources\SerialNumbers\SerialNumberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSerialNumber extends EditRecord
{
    protected static string $resource = SerialNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
