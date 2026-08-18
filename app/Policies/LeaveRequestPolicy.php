<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function view(User $user, LeaveRequest $request): bool
    {
        if (!$request->belongsToTenant((int) $user->tenant_id)) {
            return false;
        }

        return !$user->hasRole(Role::Employee) || (int) $user->employee_id === (int) $request->employee_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function decide(User $user, LeaveRequest $request): bool
    {
        return $request->belongsToTenant((int) $user->tenant_id)
            && $user->role instanceof Role
            && $user->role->canApproveLeave();
    }
}
