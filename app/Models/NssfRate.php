<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NssfRate extends Model { protected $fillable = ['tenant_id','tier_name','lower_limit','upper_limit','employee_rate','employer_rate','effective_from']; protected function casts(): array { return ['lower_limit'=>'decimal:2','upper_limit'=>'decimal:2','employee_rate'=>'decimal:6','employer_rate'=>'decimal:6','effective_from'=>'date']; } }
