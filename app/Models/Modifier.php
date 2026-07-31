<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['modifier_group_id', 'name', 'price', 'modifier_factor_id', 'is_active'])]
class Modifier extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function modifierGroup(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class);
    }

    public function modifierFactor(): BelongsTo
    {
        return $this->belongsTo(ModifierFactor::class);
    }

    /**
     * The price actually charged: the modifier's own price scaled by its
     * factor's multiplier, if one is set (e.g. a $0.50 "Shot" modifier with
     * the "Double" factor charges $1.00). No factor means no scaling.
     *
     * This is the only place modifier pricing is computed — POS, eMenu and
     * the shared BuildsModifierGroupsPayload trait all read through here, so
     * the client-displayed price and the server-trusted checkout price can
     * never drift apart.
     */
    public function effectivePrice(): float
    {
        return round((float) $this->price * (float) ($this->modifierFactor?->multiplier ?? 1.0), 2);
    }
}
