<?php

namespace App\Filament\Resources\Shifts\Tables;

use App\Support\TableFilters;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('opened_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('Shift #')->sortable(),
                TextColumn::make('branch.name')->searchable()->sortable(),
                TextColumn::make('user.name')->label('Cashier')->searchable()->sortable(),
                TextColumn::make('opened_at')->dateTime()->sortable(),
                TextColumn::make('closed_at')->dateTime()->sortable()->placeholder('Still open'),
                TextColumn::make('opening_amount')->money()->sortable(),
                TextColumn::make('closing_amount')->money()->sortable()->placeholder('—'),
                IconColumn::make('status')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->getStateUsing(fn ($record) => $record->status === 'closed')
                    ->color(fn ($record) => $record->status === 'closed' ? 'gray' : 'success'),
            ])
            ->filters([
                SelectFilter::make('branch_id')->relationship('branch', 'name')->searchable()->preload()->label('Branch'),
                SelectFilter::make('user_id')->relationship('user', 'name')->searchable()->preload()->label('Cashier'),
                SelectFilter::make('status')->options(['open' => 'Open', 'closed' => 'Closed'])->searchable(),
                TableFilters::dateRange('opened_at', 'Opened'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
