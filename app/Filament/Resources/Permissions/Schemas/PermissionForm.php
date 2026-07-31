<?php

namespace App\Filament\Resources\Permissions\Schemas;

use Filament\Schemas\Schema;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('name')->required(),
                \Filament\Forms\Components\TextInput::make('guard_name')->required()
            ]);
    }
}
