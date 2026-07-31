<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_company');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->can('view_company');
    }

    public function create(User $user): bool
    {
        return $user->can('create_company');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->can('update_company');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->can('delete_company');
    }

    public function restore(User $user, Company $company): bool
    {
        return $user->can('restore_company');
    }

    public function forceDelete(User $user, Company $company): bool
    {
        return $user->can('force_delete_company');
    }
}