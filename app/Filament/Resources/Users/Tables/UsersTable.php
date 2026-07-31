<?php

namespace App\Filament\Resources\Users\Tables;

use App\Support\TableFilters;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('id')->searchable()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('email_verified_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                \Filament\Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                \Filament\Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                \Filament\Tables\Columns\TextColumn::make('company.name')->searchable()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('branch.name')->searchable()->sortable()
            ])
            ->filters([
                SelectFilter::make('company_id')->relationship('company', 'name')->searchable()->preload()->label('Company'),
                SelectFilter::make('branch_id')->relationship('branch', 'name')->searchable()->preload()->label('Branch'),
                SelectFilter::make('roles')->relationship('roles', 'name')->searchable()->preload()->label('Role'),
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
