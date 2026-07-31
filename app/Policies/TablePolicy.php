<?php

namespace App\Policies;

use App\Models\Table;
use App\Models\User;

class TablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_table');
    }

    public function view(User $user, Table $table): bool
    {
        return $user->can('view_table');
    }

    public function create(User $user): bool
    {
        return $user->can('create_table');
    }

    public function update(User $user, Table $table): bool
    {
        return $user->can('update_table');
    }

    public function delete(User $user, Table $table): bool
    {
        return $user->can('delete_table');
    }

    public function restore(User $user, Table $table): bool
    {
        return $user->can('restore_table');
    }

    public function forceDelete(User $user, Table $table): bool
    {
        return $user->can('force_delete_table');
    }
}