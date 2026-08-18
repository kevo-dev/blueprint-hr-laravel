<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TaxRelief extends Model { protected $fillable = ['tenant_id','relief_name','monthly_amount','effective_from']; protected function casts(): array { return ['monthly_amount'=>'decimal:2','effective_from'=>'date']; } }
