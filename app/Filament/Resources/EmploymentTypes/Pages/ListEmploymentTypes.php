<?php

namespace App\\Filament\\Resources\\EmploymentTypes\\Pages;

use App\\Filament\\Resources\\EmploymentTypes\\EmploymentTypeResource;
use Filament\\Resources\\Pages\\ListRecords;
use Filament\\Actions;

class ListEmploymentTypes extends ListRecords
{
    protected static string $resource = EmploymentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\\CreateAction::make()];
    }
}
