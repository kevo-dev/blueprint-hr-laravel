<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PayrollRun extends Model { protected $fillable = ['tenant_id','payroll_period_id','total_employees','total_gross','total_paye','total_nssf','total_shif','total_housing_levy','total_net','processed_by','status','processed_at']; protected function casts(): array { return ['total_gross'=>'decimal:2','total_paye'=>'decimal:2','total_nssf'=>'decimal:2','total_shif'=>'decimal:2','total_housing_levy'=>'decimal:2','total_net'=>'decimal:2','processed_at'=>'datetime']; } public function period(){return $this->belongsTo(PayrollPeriod::class,'payroll_period_id');} }
