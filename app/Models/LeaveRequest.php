<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class LeaveRequest extends Model {
    use BelongsToTenant;
 protected $fillable = ['tenant_id','employee_id','leave_type_id','start_date','end_date','days_requested','reason','status','approved_by','approved_at','decision_comment']; protected function casts(): array { return ['start_date'=>'date','end_date'=>'date','days_requested'=>'decimal:2','approved_at'=>'datetime']; } public function employee(){return $this->belongsTo(Employee::class);} public function leaveType(){return $this->belongsTo(LeaveType::class);} public function approver(){return $this->belongsTo(User::class,'approved_by');} }
