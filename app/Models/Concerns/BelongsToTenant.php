<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where($this->qualifyColumn('tenant_id'), $tenantId);
    }

    public function belongsToTenant(int $tenantId): bool
    {
        return (int) $this->getAttribute('tenant_id') === $tenantId;
    }
}
