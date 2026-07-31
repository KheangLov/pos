<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Branch-scoped staff channels. Staff only receive events for their own
 * branch; Admins may listen to any branch of their company.
 */
$branchChannel = function ($user, $branchId) {
    if ((int) $user->branch_id === (int) $branchId) {
        return true;
    }

    $branch = \App\Models\Branch::find($branchId);

    return $branch
        && (int) $branch->company_id === (int) $user->company_id
        && $user->hasRole('Admin');
};

Broadcast::channel('branch.{branchId}.kds', $branchChannel);
Broadcast::channel('branch.{branchId}.tables', $branchChannel);
Broadcast::channel('branch.{branchId}.inventory', $branchChannel);
