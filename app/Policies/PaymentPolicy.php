<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_payment');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('view_payment');
    }

    public function create(User $user): bool
    {
        return $user->can('create_payment');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->can('update_payment');
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->can('delete_payment');
    }

    public function restore(User $user, Payment $payment): bool
    {
        return $user->can('restore_payment');
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return $user->can('force_delete_payment');
    }
}