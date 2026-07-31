<?php

namespace App\Policies;

use App\Models\SerialNumber;
use App\Models\User;

class SerialNumberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_serial_number');
    }

    public function view(User $user, SerialNumber $serialNumber): bool
    {
        return $user->can('view_serial_number');
    }

    public function create(User $user): bool
    {
        return $user->can('create_serial_number');
    }

    public function update(User $user, SerialNumber $serialNumber): bool
    {
        return $user->can('update_serial_number');
    }

    public function delete(User $user, SerialNumber $serialNumber): bool
    {
        return $user->can('delete_serial_number');
    }

    public function restore(User $user, SerialNumber $serialNumber): bool
    {
        return $user->can('restore_serial_number');
    }

    public function forceDelete(User $user, SerialNumber $serialNumber): bool
    {
        return $user->can('force_delete_serial_number');
    }
}