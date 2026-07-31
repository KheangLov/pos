<?php

namespace App\Filament\Resources\Categories\Tables;

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

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->searchable()->sortable(),
                TextColumn::make('company.name')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->sortable(),
                TextColumn::make('description')->searchable()->sortable(),
                // ->visibility('public'): without it, ImageColumn defaults any
                // non-'public'-named disk to signed URLs built against the
                // internal endpoint (http://minio:9000), which a browser can
                // never reach. The bucket is deliberately public (see
                // app:ensure-minio-bucket), so the plain public URL is correct.
                ImageColumn::make('image_url')->disk('minio')->visibility('public')->defaultImageUrl(asset('images/product-placeholder.svg')),
                IconColumn::make('is_active')->boolean()->sortable(),
                TextColumn::make('sort_order')->searchable()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company_id')->relationship('company', 'name')->searchable()->preload()->label('Company'),
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
