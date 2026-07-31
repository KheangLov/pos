<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_shift');
    }

    public function view(User $user, Shift $shift): bool
    {
        return $user->can('view_shift');
    }

    public function create(User $user): bool
    {
        return $user->can('create_shift');
    }

    public function update(User $user, Shift $shift): bool
    {
        return $user->can('update_shift');
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $user->can('delete_shift');
    }

    public function restore(User $user, Shift $shift): bool
    {
        return $user->can('restore_shift');
    }

    public function forceDelete(User $user, Shift $shift): bool
    {
        return $user->can('force_delete_shift');
    }
}