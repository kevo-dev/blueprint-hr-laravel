<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\Pages\TenantCreateRecord;
use App\Models\User;

class CreateLeaveRequest extends TenantCreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if ($user instanceof User && $user->hasRole('Employee')) {
            $data['employee_id'] = $user->employee_id;
        }

        $data['status'] = 'Pending';

        return $data;
    }
}