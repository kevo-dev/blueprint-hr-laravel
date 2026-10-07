<?php

namespace App\\Filament\\Resources\\Departments\\Pages;

use App\\Filament\\Resources\\Departments\\DepartmentResource;
use Filament\\Resources\\Pages\\ListRecords;
use Filament\\Actions;

class ListDepartments extends ListRecords
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\\CreateAction::make()];
    }
}
