<?php

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\CreateRecord;

abstract class TenantCreateRecord extends CreateRecord
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = (int) auth()->user()->tenant_id;

        return $data;
    }
}
