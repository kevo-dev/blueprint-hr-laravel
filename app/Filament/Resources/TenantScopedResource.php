<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

abstract class TenantScopedResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        if (! $user?->tenant_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('tenant_id', (int) $user->tenant_id);
    }
}
