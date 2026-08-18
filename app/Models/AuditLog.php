<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model {
    use BelongsToTenant;
 protected $fillable = ['tenant_id','user_id','action','entity_type','entity_id','before','after','details','ip_address']; protected function casts(): array { return ['before'=>'array','after'=>'array']; } public function user(){return $this->belongsTo(User::class);} }
