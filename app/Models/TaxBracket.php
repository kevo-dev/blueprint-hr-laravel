<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class TaxBracket extends Model {
    use BelongsToTenant;
 protected $fillable = ['tenant_id','band_order','lower_limit','upper_limit','rate','effective_from']; protected function casts(): array { return ['lower_limit'=>'decimal:2','upper_limit'=>'decimal:2','rate'=>'decimal:6','effective_from'=>'date']; } }
