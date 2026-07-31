<?php

namespace App\Filament\Resources\ProductVariants\Tables;

use App\Support\TableFilters;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductVariantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('id')->searchable()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('product.name')->searchable()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('additional_price')->money()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('sku')->searchable()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('barcode')->searchable()->sortable(),
                \Filament\Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                \Filament\Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true)
            ])
            ->filters([
                SelectFilter::make('product_id')->relationship('product', 'name')->searchable()->preload()->label('Product'),
                TernaryFilter::make('is_active'),
                TableFilters::dateRange(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
