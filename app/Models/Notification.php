<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Notification extends Model { protected $fillable = ['tenant_id','user_id','title','message','read_at']; protected function casts(): array { return ['read_at'=>'datetime']; } }
