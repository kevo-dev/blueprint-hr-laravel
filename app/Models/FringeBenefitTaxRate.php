<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FringeBenefitTaxRate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'effective_from', 'effective_to', 'prescribed_annual_rate', 'fbt_tax_rate', 'source_reference', 'approved_by'];
    protected function casts(): array { return ['effective_from' => 'date', 'effective_to' => 'date', 'prescribed_annual_rate' => 'decimal:4', 'fbt_tax_rate' => 'decimal:4']; }
}