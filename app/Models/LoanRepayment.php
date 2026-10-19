<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LoanRepayment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'employee_loan_id', 'payroll_period_id', 'amount', 'balance_after'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'balance_after' => 'decimal:2']; }
    public function loan() { return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id'); }
    public function period() { return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id'); }
}