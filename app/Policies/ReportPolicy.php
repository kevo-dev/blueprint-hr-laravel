<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\PayrollTransaction;
use App\Models\User;

class ReportPolicy
{
    public function exportEmployees(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->role instanceof Role
            && in_array($user->role, [Role::SuperAdmin, Role::CompanyAdmin, Role::HRManager], true);
    }

    public function downloadPayslip(User $user, PayrollTransaction $transaction): bool
    {
        return (new PayrollTransactionPolicy())->view($user, $transaction);
    }
}
