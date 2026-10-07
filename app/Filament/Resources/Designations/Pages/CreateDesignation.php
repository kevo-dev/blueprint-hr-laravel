<?php

namespace App\\Filament\\Resources\\Designations\\Pages;

use App\\Filament\\Resources\\Designations\\DesignationResource;
use App\\Filament\\Resources\\Pages\\TenantCreateRecord;

class CreateDesignation extends TenantCreateRecord
{
    protected static string $resource = DesignationResource::class;
}
