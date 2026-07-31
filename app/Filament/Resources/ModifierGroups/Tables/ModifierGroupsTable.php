<?php

namespace App\Filament\Resources\ModifierGroups\Tables;

use App\Support\TableFilters;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ModifierGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->searchable()->sortable(),
                TextColumn::make('company.name')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('selection_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->color(fn (string $state): string => match ($state) {
                        'single' => 'info',
                        'multiple' => 'success',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('min_selections')->searchable()->sortable(),
                TextColumn::make('max_selections')->searchable()->sortable(),
                IconColumn::make('is_active')->boolean()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company_id')->relationship('company', 'name')->searchable()->preload()->label('Company'),
                SelectFilter::make('selection_type')->options(['single' => 'Single', 'multiple' => 'Multiple'])->searchable(),
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
