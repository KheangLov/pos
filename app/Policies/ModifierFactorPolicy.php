<?php

namespace App\Policies;

use App\Models\ModifierFactor;
use App\Models\User;

class ModifierFactorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_modifier_factor');
    }

    public function view(User $user, ModifierFactor $modifierFactor): bool
    {
        return $user->can('view_modifier_factor');
    }

    public function create(User $user): bool
    {
        return $user->can('create_modifier_factor');
    }

    public function update(User $user, ModifierFactor $modifierFactor): bool
    {
        return $user->can('update_modifier_factor');
    }

    public function delete(User $user, ModifierFactor $modifierFactor): bool
    {
        return $user->can('delete_modifier_factor');
    }

    public function restore(User $user, ModifierFactor $modifierFactor): bool
    {
        return $user->can('restore_modifier_factor');
    }

    public function forceDelete(User $user, ModifierFactor $modifierFactor): bool
    {
        return $user->can('force_delete_modifier_factor');
    }
}