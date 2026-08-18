<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class TaxRelief extends Model {
    use BelongsToTenant;
 protected $fillable = ['tenant_id','relief_name','monthly_amount','effective_from']; protected function casts(): array { return ['monthly_amount'=>'decimal:2','effective_from'=>'date']; } }
