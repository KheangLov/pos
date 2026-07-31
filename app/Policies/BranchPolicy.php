<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_branch');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->can('view_branch');
    }

    public function create(User $user): bool
    {
        return $user->can('create_branch');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('update_branch');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can('delete_branch');
    }

    public function restore(User $user, Branch $branch): bool
    {
        return $user->can('restore_branch');
    }

    public function forceDelete(User $user, Branch $branch): bool
    {
        return $user->can('force_delete_branch');
    }
}