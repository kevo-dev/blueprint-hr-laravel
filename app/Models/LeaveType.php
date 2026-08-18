<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class LeaveType extends Model {
    use BelongsToTenant;
 protected $fillable = ['tenant_id','name','default_days','paid','description']; public function balances(){return $this->hasMany(LeaveBalance::class);} }
