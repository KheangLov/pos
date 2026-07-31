<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\StockTransaction;
use App\Support\TableFilters;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Every stock transaction (POS/eMenu sales, and manual admin
            // purchases/adjustments via StockTransactionForm) broadcasts
            // App\Events\StockLow when it drops a product below the reorder
            // threshold — see NotifyLowStock. Polling this table is the other
            // half: it keeps the "On hand" figure itself current for whoever
            // has this list open, the same way the panel already polls
            // database notifications every 30s.
            ->poll('10s')
            ->columns([
                TextColumn::make('id')->searchable()->sortable(),
                TextColumn::make('company.name')->searchable()->sortable(),
                TextColumn::make('category.name')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->sortable(),
                TextColumn::make('description')->searchable()->sortable(),
                // ->visibility('public'): without it, ImageColumn defaults any
                // non-'public'-named disk to signed URLs built against the
                // internal endpoint (http://minio:9000), which a browser can
                // never reach. The bucket is deliberately public (see
                // app:ensure-minio-bucket), so the plain public URL is correct.
                ImageColumn::make('image_url')->disk('minio')->visibility('public')->defaultImageUrl(asset('images/product-placeholder.svg')),
                TextColumn::make('base_price')->money()->sortable(),
                TextColumn::make('cost_price')->money()->sortable(),
                TextColumn::make('on_hand')
                    ->label('On hand')
                    ->state(fn ($record): int => (int) StockTransaction::query()
                        ->where('product_id', $record->id)
                        ->whereHas('branch', fn (Builder $query) => $query->where('company_id', $record->company_id))
                        ->sum('quantity'))
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state <= StockTransaction::LOW_STOCK_THRESHOLD => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('sku')->searchable()->sortable(),
                TextColumn::make('barcode')->searchable()->sortable(),
                IconColumn::make('is_serialized')->boolean()->sortable(),
                TextColumn::make('warranty_period')->searchable()->sortable(),
                IconColumn::make('is_active')->boolean()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company_id')->relationship('company', 'name')->searchable()->preload()->label('Company'),
                SelectFilter::make('category_id')->relationship('category', 'name')->searchable()->preload()->label('Category'),
                TernaryFilter::make('is_active'),
                TernaryFilter::make('is_serialized'),
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
