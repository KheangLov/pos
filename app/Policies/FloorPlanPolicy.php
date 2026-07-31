<?php

namespace App\Policies;

use App\Models\FloorPlan;
use App\Models\User;

class FloorPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_floor_plan');
    }

    public function view(User $user, FloorPlan $floorPlan): bool
    {
        return $user->can('view_floor_plan');
    }

    public function create(User $user): bool
    {
        return $user->can('create_floor_plan');
    }

    public function update(User $user, FloorPlan $floorPlan): bool
    {
        return $user->can('update_floor_plan');
    }

    public function delete(User $user, FloorPlan $floorPlan): bool
    {
        return $user->can('delete_floor_plan');
    }

    public function restore(User $user, FloorPlan $floorPlan): bool
    {
        return $user->can('restore_floor_plan');
    }

    public function forceDelete(User $user, FloorPlan $floorPlan): bool
    {
        return $user->can('force_delete_floor_plan');
    }
}