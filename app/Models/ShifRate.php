<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShifRate extends Model { protected $fillable = ['tenant_id','percentage','min_amount','effective_from']; protected function casts(): array { return ['percentage'=>'decimal:6','min_amount'=>'decimal:2','effective_from'=>'date']; } }
