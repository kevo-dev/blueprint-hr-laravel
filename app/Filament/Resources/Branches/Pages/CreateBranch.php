<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Pages\TenantCreateRecord;

class CreateBranch extends TenantCreateRecord
{
    protected static string $resource = BranchResource::class;
}
