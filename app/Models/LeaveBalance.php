<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class LeaveBalance extends Model {
    use BelongsToTenant;
 protected $fillable = ['tenant_id','employee_id','leave_type_id','year','allocated_days','used_days','carried_forward']; protected function casts(): array { return ['allocated_days'=>'decimal:2','used_days'=>'decimal:2','carried_forward'=>'decimal:2']; } public function employee(){return $this->belongsTo(Employee::class);} public function leaveType(){return $this->belongsTo(LeaveType::class);} public function getAvailableDaysAttribute(): float { return (float)$this->allocated_days + (float)$this->carried_forward - (float)$this->used_days; } }
