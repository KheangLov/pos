<?php

namespace App\Policies;

use App\Models\StockTransaction;
use App\Models\User;

class StockTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_stock_transaction');
    }

    public function view(User $user, StockTransaction $stockTransaction): bool
    {
        return $user->can('view_stock_transaction');
    }

    public function create(User $user): bool
    {
        return $user->can('create_stock_transaction');
    }

    public function update(User $user, StockTransaction $stockTransaction): bool
    {
        return $user->can('update_stock_transaction');
    }

    public function delete(User $user, StockTransaction $stockTransaction): bool
    {
        return $user->can('delete_stock_transaction');
    }

    public function restore(User $user, StockTransaction $stockTransaction): bool
    {
        return $user->can('restore_stock_transaction');
    }

    public function forceDelete(User $user, StockTransaction $stockTransaction): bool
    {
        return $user->can('force_delete_stock_transaction');
    }
}