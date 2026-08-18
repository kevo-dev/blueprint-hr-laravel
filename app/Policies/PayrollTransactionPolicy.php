<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\PayrollTransaction;
use App\Models\User;

class PayrollTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, PayrollTransaction $transaction): bool
    {
        if (!$transaction->belongsToTenant((int) $user->tenant_id)) {
            return false;
        }

        return ($user->role instanceof Role && ($user->role->canProcessPayroll() || $user->role->canManagePeople()))
            || ($user->hasRole(Role::Employee) && (int) $user->employee_id === (int) $transaction->employee_id);
    }
}
