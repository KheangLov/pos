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
                            ->state(fn (Activity $record) => static::changes($record)['attributes'] ?? []),
                        KeyValueEntry::make('old_values')
                            ->label('Previous values')
                            ->state(fn (Activity $record) => static::changes($record)['old'] ?? []),
                    ])
                    ->visible(fn (Activity $record) => filled(static::changes($record))),
            ]);
    }

    /**
     * Rows logged before spatie/laravel-activitylog was pinned to ^4.12 (2026-08-11) were
     * written by a transiently-resolved 5.x install, which stores changes in the
     * `attribute_changes` column instead of 4.x's `properties`. Read both so old audit
     * history stays visible alongside everything logged from now on.
     */
    private static function changes(Activity $record): array
    {
        if (filled($record->properties)) {
            return $record->properties->toArray();
        }

        return $record->attribute_changes ? (json_decode($record->attribute_changes, true) ?? []) : [];
    }
}
