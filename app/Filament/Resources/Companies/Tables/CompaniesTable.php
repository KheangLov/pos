<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Models\Company;
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

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->searchable()->sortable(),
                // Uploads are stored on the 'minio' disk (see CompanyForm's
                // FileUpload) — without ->disk('minio') this resolves against
                // the default disk instead. ->visibility('public') matters too:
                // left unset, ImageColumn defaults any non-'public'-named disk
                // to a signed URL built against the internal endpoint
                // (http://minio:9000), which a browser can never reach. The
                // bucket is deliberately public (see app:ensure-minio-bucket).
                ImageColumn::make('logo_url')
                    ->disk('minio')
                    ->visibility('public')
                    ->label('')
                    ->circular()
                    ->size(32)
                    ->defaultImageUrl(asset('images/company-placeholder.svg')),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('business_type')
                    ->label('Business type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? Company::businessTypes()[$state] ?? $state : '—')
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('phone')->searchable()->sortable(),
                TextColumn::make('address')->searchable()->sortable(),
                IconColumn::make('is_active')->boolean()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('business_type')->label('Business type')->options(Company::businessTypes())->searchable(),
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
