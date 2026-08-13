<?php

namespace App\Filament\Resources\StockTransactions;

use App\Filament\Resources\StockTransactions\Pages\CreateStockTransaction;
use App\Filament\Resources\StockTransactions\Pages\EditStockTransaction;
use App\Filament\Resources\StockTransactions\Pages\ListStockTransactions;
use App\Filament\Resources\StockTransactions\Schemas\StockTransactionForm;
use App\Filament\Resources\StockTransactions\Tables\StockTransactionsTable;
use App\Models\StockTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockTransactionResource extends Resource
{
    protected static ?string $model = StockTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    /**
     * StockTransaction has no company_id of its own — scoped through branch,
     * same pattern as InvoiceResource.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('branch', fn (Builder $query) => $query->where('company_id', auth()->user()->company_id));
    }

    public static function form(Schema $schema): Schema
    {
        return StockTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockTransactionsTable::configure($table);
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
            'index' => ListStockTransactions::route('/'),
            'create' => CreateStockTransaction::route('/create'),
            'edit' => EditStockTransaction::route('/{record}/edit'),
        ];
    }
}
