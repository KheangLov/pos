<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\User;
use App\Support\TableFilters;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('causer.name')->label('Who')->searchable()->sortable()->placeholder('System'),
                TextColumn::make('event')->badge()->sortable()
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_id')->label('Subject ID')->sortable(),
                TextColumn::make('description')->searchable()->limit(60),
                TextColumn::make('log_name')->label('Log')->badge()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->options([
                    'created' => 'Created',
                    'updated' => 'Updated',
                    'deleted' => 'Deleted',
                ])->searchable(),
                SelectFilter::make('subject_type')
                    ->label('Subject')
                    ->options(fn () => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn ($type) => [$type => class_basename($type)])
                        ->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('causer_id')
                    ->label('Who')
                    ->options(fn () => User::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $userId) => $q->where('causer_type', User::class)->where('causer_id', $userId),
                    )),
                TableFilters::dateRange(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
