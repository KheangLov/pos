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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

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

    /**
     * Serial tracking only makes sense for businesses selling individually
     * trackable goods (see Company::usesSerialNumbers()) — a coffee shop or
     * restaurant has no use for it. Combined with, not instead of, the
     * existing view_serial_number permission check (SerialNumberPolicy) —
     * business_type narrows who the feature applies to at all, it isn't a
     * substitute for the role-based check that already denies Cashier/Kitchen.
     * Filament's navigation builder already calls canAccess() (→ canViewAny())
     * before rendering the sidebar link, so this alone also hides it there —
     * no separate shouldRegisterNavigation() override needed.
     */
    public static function canViewAny(): bool
    {
        return (auth()->user()?->company?->usesSerialNumbers() ?? false) && parent::canViewAny();
    }
}
