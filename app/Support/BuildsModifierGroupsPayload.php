<?php

namespace App\Support;

use App\Models\Modifier;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Shared by POS and eMenu: the client-side (Alpine/Livewire) shape used to
 * render a modifier picker and to decide whether a product needs one at all.
 */
trait BuildsModifierGroupsPayload
{
    /**
     * @return array<int, array{id: int, name: string, selection_type: string, min_selections: int, max_selections: int|null, modifiers: array<int, array{id: int, name: string, price: float}>}>
     */
    protected function modifierGroupsPayload(Product $product): array
    {
        return $product->modifierGroups
            ->where('is_active', true)
            ->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'selection_type' => $group->selection_type,
                'min_selections' => $group->min_selections,
                'max_selections' => $group->max_selections,
                'modifiers' => $group->modifiers
                    ->where('is_active', true)
                    ->map(fn ($modifier) => [
                        'id' => $modifier->id,
                        'name' => $modifier->name,
                        'price' => $modifier->effectivePrice(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->filter(fn ($group) => $group['modifiers'] !== [])
            ->values()
            ->all();
    }

    /**
     * Server-side pricing/security: only modifiers that belong to a group
     * actually attached to this product may be applied. Client-supplied ids
     * are a wishlist, never trusted for identity or price.
     *
     * @param  array<int, array{id?: int}>  $requestedModifiers
     * @return Collection<int, Modifier>
     */
    protected function resolveValidModifiers(Product $product, array $requestedModifiers): Collection
    {
        $allowedModifierIds = $product->modifierGroups->flatMap->modifiers->pluck('id');
        $requestedModifierIds = collect($requestedModifiers)->pluck('id');

        return $product->modifierGroups
            ->flatMap->modifiers
            ->whereIn('id', $requestedModifierIds->intersect($allowedModifierIds));
    }
}
