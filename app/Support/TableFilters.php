<?php

namespace App\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class TableFilters
{
    /**
     * A from/until date-range filter for the given column, shared across
     * resource tables so each one doesn't hand-roll the same query logic.
     */
    public static function dateRange(string $column = 'created_at', string $label = 'Created'): Filter
    {
        return Filter::make($column)
            ->schema([
                // native(false): Filament's own JS calendar popup instead of
                // the plain browser <input type="date">, consistent everywhere
                // a date is picked in the app (see also Dashboard.php).
                DatePicker::make('from')->label("{$label} from")->native(false),
                DatePicker::make('until')->label("{$label} until")->native(false),
            ])
            ->query(function (Builder $query, array $data) use ($column): Builder {
                return $query
                    ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate($column, '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate($column, '<=', $date));
            })
            ->indicateUsing(function (array $data) use ($label): array {
                $indicators = [];

                if ($data['from'] ?? null) {
                    $indicators[] = $label.' from '.$data['from'];
                }

                if ($data['until'] ?? null) {
                    $indicators[] = $label.' until '.$data['until'];
                }

                return $indicators;
            });
    }
}
