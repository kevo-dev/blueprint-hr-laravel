<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EmployeeLoan extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'employee_id', 'loan_number', 'loan_type', 'principal_amount', 'annual_interest_rate', 'monthly_repayment', 'disbursed_at', 'first_repayment_date', 'term_months', 'status', 'notes'];

    protected function casts(): array
    {
        return ['principal_amount' => 'decimal:2', 'annual_interest_rate' => 'decimal:4', 'monthly_repayment' => 'decimal:2', 'disbursed_at' => 'date', 'first_repayment_date' => 'date'];
    }

    public function employee() { return $this->belongsTo(Employee::class); }
    public function repayments() { return $this->hasMany(LoanRepayment::class); }
    public function getOutstandingBalanceAttribute(): float
    {
        return max(0, (float) $this->principal_amount - (float) $this->repayments()->sum('principal_component'));
    }
}