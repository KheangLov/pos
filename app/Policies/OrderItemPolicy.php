<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;

class OrderItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_order_item');
    }

    public function view(User $user, OrderItem $orderItem): bool
    {
        return $user->can('view_order_item');
    }

    public function create(User $user): bool
    {
        return $user->can('create_order_item');
    }

    public function update(User $user, OrderItem $orderItem): bool
    {
        return $user->can('update_order_item');
    }

    public function delete(User $user, OrderItem $orderItem): bool
    {
        return $user->can('delete_order_item');
    }

    public function restore(User $user, OrderItem $orderItem): bool
    {
        return $user->can('restore_order_item');
    }

    public function forceDelete(User $user, OrderItem $orderItem): bool
    {
        return $user->can('force_delete_order_item');
    }
}