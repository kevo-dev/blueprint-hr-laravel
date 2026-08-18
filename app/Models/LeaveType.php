<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LeaveType extends Model { protected $fillable = ['tenant_id','name','default_days','paid','description']; public function balances(){return $this->hasMany(LeaveBalance::class);} }
