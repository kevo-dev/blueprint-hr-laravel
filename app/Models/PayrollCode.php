<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PayrollCode extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'code', 'name', 'kind', 'calculation_method', 'default_amount', 'default_percentage', 'percentage_basis', 'taxable', 'affects_nssf', 'affects_shif', 'affects_housing_levy', 'active', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['default_amount' => 'decimal:2', 'default_percentage' => 'decimal:4', 'taxable' => 'boolean', 'affects_nssf' => 'boolean', 'affects_shif' => 'boolean', 'affects_housing_levy' => 'boolean', 'active' => 'boolean', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function employeeItems() { return $this->hasMany(EmployeePayrollItem::class); }
}