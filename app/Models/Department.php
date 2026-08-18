<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class Department extends Model {
    use BelongsToTenant;
 protected $fillable = ['tenant_id','branch_id','name','code']; public function branch(){return $this->belongsTo(Branch::class);} public function tenant(){return $this->belongsTo(Tenant::class);} }
