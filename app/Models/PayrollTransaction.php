<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PayrollTransaction extends Model {
    use BelongsToTenant;
 protected $fillable = ['tenant_id','payroll_period_id','employee_id','basic_salary','allowances','gross_pay','taxable_pay','paye','personal_relief','nssf','shif','housing_levy','other_deductions','total_deductions','net_pay','status']; protected function casts(): array { return ['basic_salary'=>'decimal:2','allowances'=>'decimal:2','gross_pay'=>'decimal:2','taxable_pay'=>'decimal:2','paye'=>'decimal:2','personal_relief'=>'decimal:2','nssf'=>'decimal:2','shif'=>'decimal:2','housing_levy'=>'decimal:2','other_deductions'=>'decimal:2','total_deductions'=>'decimal:2','net_pay'=>'decimal:2']; } public function period(){return $this->belongsTo(PayrollPeriod::class,'payroll_period_id');} public function employee(){return $this->belongsTo(Employee::class);} }
