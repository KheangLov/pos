<?php

namespace App\Policies;

use App\Models\Discount;
use App\Models\User;

class DiscountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_discount');
    }

    public function view(User $user, Discount $discount): bool
    {
        return $user->can('view_discount');
    }

    public function create(User $user): bool
    {
        return $user->can('create_discount');
    }

    public function update(User $user, Discount $discount): bool
    {
        return $user->can('update_discount');
    }

    public function delete(User $user, Discount $discount): bool
    {
        return $user->can('delete_discount');
    }

    public function restore(User $user, Discount $discount): bool
    {
        return $user->can('restore_discount');
    }

    public function forceDelete(User $user, Discount $discount): bool
    {
        return $user->can('force_delete_discount');
    }
}