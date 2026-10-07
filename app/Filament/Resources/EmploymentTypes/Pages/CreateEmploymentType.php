<?php

namespace App\Filament\Resources\EmploymentTypes\Pages;

use App\Filament\Resources\EmploymentTypes\EmploymentTypeResource;
use App\Filament\Resources\Pages\TenantCreateRecord;

class CreateEmploymentType extends TenantCreateRecord
{
    protected static string $resource = EmploymentTypeResource::class;
}
