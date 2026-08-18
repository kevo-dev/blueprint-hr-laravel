<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Employee $employee): bool
    {
        if (!$employee->belongsToTenant((int) $user->tenant_id)) {
            return false;
        }

        return !$user->hasRole(Role::Employee) || (int) $user->employee_id === (int) $employee->id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->role instanceof Role && $user->role->canManagePeople();
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->create($user) && $employee->belongsToTenant((int) $user->tenant_id);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->update($user, $employee);
    }
}
