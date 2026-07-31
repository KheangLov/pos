<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Activitylog\Models\Activity;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('created_at')->label('When')->dateTime(),
                            TextEntry::make('causer.name')->label('Who')->placeholder('System'),
                            TextEntry::make('event')->badge(),
                            TextEntry::make('log_name')->label('Log')->badge(),
                        ]),
                        TextEntry::make('description'),
                        Grid::make(2)->schema([
                            TextEntry::make('subject_type')->label('Subject type')
                                ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                            TextEntry::make('subject_id')->label('Subject ID'),
                        ]),
                    ]),

                Section::make('Changed attributes')
                    ->schema([
                        KeyValueEntry::make('new_values')
                            ->label('New values')
                            ->state(fn (Activity $record) => (array) ($record->attribute_changes['attributes'] ?? [])),
                        KeyValueEntry::make('old_values')
                            ->label('Previous values')
                            ->state(fn (Activity $record) => (array) ($record->attribute_changes['old'] ?? [])),
                    ])
                    ->visible(fn (Activity $record) => filled($record->attribute_changes?->toArray())),
            ]);
    }
}
