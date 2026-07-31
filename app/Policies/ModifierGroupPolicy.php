<?php

namespace App\Policies;

use App\Models\ModifierGroup;
use App\Models\User;

class ModifierGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_modifier_group');
    }

    public function view(User $user, ModifierGroup $modifierGroup): bool
    {
        return $user->can('view_modifier_group');
    }

    public function create(User $user): bool
    {
        return $user->can('create_modifier_group');
    }

    public function update(User $user, ModifierGroup $modifierGroup): bool
    {
        return $user->can('update_modifier_group');
    }

    public function delete(User $user, ModifierGroup $modifierGroup): bool
    {
        return $user->can('delete_modifier_group');
    }

    public function restore(User $user, ModifierGroup $modifierGroup): bool
    {
        return $user->can('restore_modifier_group');
    }

    public function forceDelete(User $user, ModifierGroup $modifierGroup): bool
    {
        return $user->can('force_delete_modifier_group');
    }
}