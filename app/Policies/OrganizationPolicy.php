<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class OrganizationPolicy
{
    public function manage(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->role instanceof Role
            && $user->role->canManagePeople();
    }
}
