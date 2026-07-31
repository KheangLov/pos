<?php

namespace App\Policies;

use App\Models\Modifier;
use App\Models\User;

class ModifierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_modifier');
    }

    public function view(User $user, Modifier $modifier): bool
    {
        return $user->can('view_modifier');
    }

    public function create(User $user): bool
    {
        return $user->can('create_modifier');
    }

    public function update(User $user, Modifier $modifier): bool
    {
        return $user->can('update_modifier');
    }

    public function delete(User $user, Modifier $modifier): bool
    {
        return $user->can('delete_modifier');
    }

    public function restore(User $user, Modifier $modifier): bool
    {
        return $user->can('restore_modifier');
    }

    public function forceDelete(User $user, Modifier $modifier): bool
    {
        return $user->can('force_delete_modifier');
    }
}