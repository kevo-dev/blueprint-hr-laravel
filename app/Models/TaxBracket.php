<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TaxBracket extends Model { protected $fillable = ['tenant_id','band_order','lower_limit','upper_limit','rate','effective_from']; protected function casts(): array { return ['lower_limit'=>'decimal:2','upper_limit'=>'decimal:2','rate'=>'decimal:6','effective_from'=>'date']; } }
