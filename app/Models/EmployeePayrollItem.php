<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EmployeePayrollItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'employee_id', 'payroll_code_id', 'calculation_method', 'amount', 'percentage', 'effective_from', 'effective_to', 'active'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'percentage' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date', 'active' => 'boolean'];
    }

    public function employee() { return $this->belongsTo(Employee::class); }
    public function payrollCode() { return $this->belongsTo(PayrollCode::class); }
}