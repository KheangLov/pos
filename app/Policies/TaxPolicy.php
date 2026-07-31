<?php

namespace App\Policies;

use App\Models\Tax;
use App\Models\User;

class TaxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_tax');
    }

    public function view(User $user, Tax $tax): bool
    {
        return $user->can('view_tax');
    }

    public function create(User $user): bool
    {
        return $user->can('create_tax');
    }

    public function update(User $user, Tax $tax): bool
    {
        return $user->can('update_tax');
    }

    public function delete(User $user, Tax $tax): bool
    {
        return $user->can('delete_tax');
    }

    public function restore(User $user, Tax $tax): bool
    {
        return $user->can('restore_tax');
    }

    public function forceDelete(User $user, Tax $tax): bool
    {
        return $user->can('force_delete_tax');
    }
}