<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Employee extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['tenant_id','employee_no','payroll_no','first_name','middle_name','last_name','gender','dob','id_no','kra_pin','nssf_no','shif_no','phone','email','branch_id','department_id','designation_id','grade_id','employment_type_id','employment_date','employment_status','basic_salary','bank_name','bank_branch','account_number'];
    protected $hidden = ['id_no','kra_pin','nssf_no','shif_no','account_number'];
    protected function casts(): array { return ['dob'=>'date','employment_date'=>'date','basic_salary'=>'decimal:2']; }
    public function tenant(){return $this->belongsTo(Tenant::class);}
    public function branch(){return $this->belongsTo(Branch::class);}
    public function department(){return $this->belongsTo(Department::class);}
    public function designation(){return $this->belongsTo(Designation::class);}
    public function grade(){return $this->belongsTo(Grade::class);}
    public function employmentType(){return $this->belongsTo(EmploymentType::class);}
    public function user(){return $this->hasOne(User::class);}
    public function leaveBalances(){return $this->hasMany(LeaveBalance::class);}
    public function leaveRequests(){return $this->hasMany(LeaveRequest::class);}
    public function payrollTransactions(){return $this->hasMany(PayrollTransaction::class);}
    public function getFullNameAttribute(): string { return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name]))); }
}
