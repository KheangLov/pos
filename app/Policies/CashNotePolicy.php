<?php

namespace App\Policies;

use App\Models\CashNote;
use App\Models\User;

class CashNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_cash_note');
    }

    public function view(User $user, CashNote $cashNote): bool
    {
        return $user->can('view_cash_note');
    }

    public function create(User $user): bool
    {
        return $user->can('create_cash_note');
    }

    public function update(User $user, CashNote $cashNote): bool
    {
        return $user->can('update_cash_note');
    }

    public function delete(User $user, CashNote $cashNote): bool
    {
        return $user->can('delete_cash_note');
    }

    public function restore(User $user, CashNote $cashNote): bool
    {
        return $user->can('restore_cash_note');
    }

    public function forceDelete(User $user, CashNote $cashNote): bool
    {
        return $user->can('force_delete_cash_note');
    }
}