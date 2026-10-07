<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\Role;

abstract class TenantScopedResource extends Resource
{
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->tenant_id;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole(Role::SuperAdmin, Role::CompanyAdmin, Role::HRManager) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return static::canCreate() && (int) $record->tenant_id === (int) auth()->user()?->tenant_id;
    }

    public static function canDelete(mixed $record): bool
    {
        return static::canEdit($record);
    }

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
