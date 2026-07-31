<?php

namespace App\Filament\Resources\SerialNumbers\Tables;

use App\Support\TableFilters;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SerialNumbersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->searchable()->sortable(),
                TextColumn::make('product.name')->searchable()->sortable(),
                TextColumn::make('productVariant.name')->searchable()->sortable(),
                TextColumn::make('branch.name')->searchable()->sortable(),
                TextColumn::make('serial_number')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->color(fn (string $state): string => match ($state) {
                        'in_stock' => 'success',
                        'sold' => 'gray',
                        default => 'secondary',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('warranty_end_date')->searchable()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_id')->relationship('product', 'name')->searchable()->preload()->label('Product'),
                SelectFilter::make('branch_id')->relationship('branch', 'name')->searchable()->preload()->label('Branch'),
                SelectFilter::make('status')->options(['in_stock' => 'In stock', 'sold' => 'Sold'])->searchable(),
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
