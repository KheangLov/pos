<?php

namespace App\Filament\Resources\Modifiers\Schemas;

use Filament\Schemas\Schema;

class ModifierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }
}
