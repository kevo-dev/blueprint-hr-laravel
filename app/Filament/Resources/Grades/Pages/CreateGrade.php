<?php

namespace App\Filament\Resources\Grades\Pages;

use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\Pages\TenantCreateRecord;

class CreateGrade extends TenantCreateRecord
{
    protected static string $resource = GradeResource::class;
}
