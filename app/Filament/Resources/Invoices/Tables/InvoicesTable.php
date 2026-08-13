<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Models\Invoice;
use App\Support\TableFilters;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('Invoice #')->searchable()->sortable(),
                TextColumn::make('branch.name')->label('Branch')->searchable()->sortable(),
                TextColumn::make('table.name')->label('Table')->placeholder('Takeaway')->searchable()->sortable(),
                TextColumn::make('user.name')->label('Cashier')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->color(fn (string $state): string => match ($state) {
                        'pending', 'draft' => 'warning',
                        'paid', 'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('total')->money()->sortable(),
                TextColumn::make('created_at')->label('Placed')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')->relationship('branch', 'name')->searchable()->preload()->label('Branch'),
                SelectFilter::make('user_id')->relationship('user', 'name')->searchable()->preload()->label('Cashier'),
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ])->searchable(),
                TableFilters::dateRange(),
            ])
            ->recordActions([
                Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->extraAttributes(fn (Invoice $record) => [
                        'onclick' => 'printReceiptFrame(\''.route('invoices.receipt', $record).'\')',
                    ]),
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
