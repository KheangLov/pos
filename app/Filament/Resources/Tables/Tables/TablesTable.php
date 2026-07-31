<?php

namespace App\Filament\Resources\Tables\Tables;

use App\Support\TableFilters;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->searchable()->sortable(),
                TextColumn::make('floorPlan.name')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('capacity')->searchable()->sortable(),
                TextColumn::make('shape')->searchable()->sortable(),
                TextColumn::make('position_x')->searchable()->sortable(),
                TextColumn::make('position_y')->searchable()->sortable(),
                TextColumn::make('width')->searchable()->sortable(),
                TextColumn::make('height')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'occupied' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('uuid')->searchable()->sortable(),
            ])
            ->filters([
                SelectFilter::make('floor_plan_id')->relationship('floorPlan', 'name')->searchable()->preload()->label('Floor Plan'),
                SelectFilter::make('status')->options(['available' => 'Available', 'occupied' => 'Occupied'])->searchable(),
                SelectFilter::make('shape')->options(['round' => 'Round', 'rectangle' => 'Rectangle'])->searchable(),
                TableFilters::dateRange(),
            ])
            ->recordActions([
                Action::make('qr_code')
                    ->label('QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->modalHeading('Table QR Code')
                    ->modalContent(fn ($record) => view('filament.components.qr-code', [
                        'url' => route('emenu.table', ['uuid' => $record->uuid]),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
