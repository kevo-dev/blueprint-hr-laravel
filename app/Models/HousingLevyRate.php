<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class HousingLevyRate extends Model { protected $fillable = ['tenant_id','employee_percentage','employer_percentage','effective_from']; protected function casts(): array { return ['employee_percentage'=>'decimal:6','employer_percentage'=>'decimal:6','effective_from'=>'date']; } }
