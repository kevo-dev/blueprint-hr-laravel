<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class Designation extends Model {
    use BelongsToTenant;
 protected $fillable = ['tenant_id','name','description']; public function tenant(){return $this->belongsTo(Tenant::class);} }
