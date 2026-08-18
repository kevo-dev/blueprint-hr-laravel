<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class Notification extends Model {
    use BelongsToTenant;
 protected $fillable = ['tenant_id','user_id','title','message','read_at']; protected function casts(): array { return ['read_at'=>'datetime']; } }
