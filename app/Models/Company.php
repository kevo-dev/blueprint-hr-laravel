<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class Company extends Model {
    use BelongsToTenant;
 protected $fillable = ['tenant_id','company_name','kra_pin','email','phone','address']; public function tenant(){return $this->belongsTo(Tenant::class);} }
