<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_invoice');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('view_invoice');
    }

    public function create(User $user): bool
    {
        return $user->can('create_invoice');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('update_invoice');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can('delete_invoice');
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return $user->can('restore_invoice');
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return $user->can('force_delete_invoice');
    }
}