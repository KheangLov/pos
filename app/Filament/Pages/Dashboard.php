<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFilters;

class Dashboard extends \Filament\Pages\Dashboard
{
    // HasFilters only — NOT HasFiltersForm. HasFiltersForm defines
    // getFiltersForm(), and the vendor Dashboard::content() embeds that
    // directly into the page whenever it's defined (regardless of whether a
    // FilterAction is also present), which produced a second, unstyled copy
    // of these same fields sitting loose on the page. HasFilters alone still
    // gives $filters + session persistence, which is all FilterAction needs.
    use HasFilters;

    protected function getHeaderActions(): array
    {
        return [
            // FilterAction ships with no fields of its own — Filament's
            // built-in dashboard FilterAction (Pages/Dashboard/Actions/
            // FilterAction.php) only wires up fillForm()/action()/slideOver(),
            // it never calls ->schema(). Without one explicitly here the
            // slide-over opens with a heading and Apply/Cancel buttons but no
            // fields at all — this list is what was missing.
            FilterAction::make()
                ->schema([
                    Select::make('branch_id')
                        ->label('Branch')
                        ->options(fn (): array => Branch::query()
                            ->where('company_id', auth()->user()->company_id)
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->placeholder('All branches'),
                    DatePicker::make('from')->label('From')->native(false),
                    DatePicker::make('until')->label('Until')->native(false),
                ]),
        ];
    }
}
