<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\PayrollPeriod;
use App\Models\User;

class PayrollPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, PayrollPeriod $period): bool
    {
        return $period->belongsToTenant((int) $user->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->role instanceof Role
            && $user->role->canProcessPayroll();
    }

    public function process(User $user, PayrollPeriod $period): bool
    {
        return $this->create($user) && $period->belongsToTenant((int) $user->tenant_id);
    }
}
