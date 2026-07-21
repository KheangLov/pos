<?php

namespace App\Filament\Resources\SerialNumbers;

use App\Filament\Resources\SerialNumbers\Pages\CreateSerialNumber;
use App\Filament\Resources\SerialNumbers\Pages\EditSerialNumber;
use App\Filament\Resources\SerialNumbers\Pages\ListSerialNumbers;
use App\Filament\Resources\SerialNumbers\Schemas\SerialNumberForm;
use App\Filament\Resources\SerialNumbers\Tables\SerialNumbersTable;
use App\Models\SerialNumber;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SerialNumberResource extends Resource
{
    protected static ?string $model = SerialNumber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SerialNumberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SerialNumbersTable::configure($table);
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
            'index' => ListSerialNumbers::route('/'),
            'create' => CreateSerialNumber::route('/create'),
            'edit' => EditSerialNumber::route('/{record}/edit'),
        ];
    }
}
