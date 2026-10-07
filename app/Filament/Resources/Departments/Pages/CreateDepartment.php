<?php

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Pages\TenantCreateRecord;

class CreateDepartment extends TenantCreateRecord
{
    protected static string $resource = DepartmentResource::class;
}
