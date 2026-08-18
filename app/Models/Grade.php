<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class Grade extends Model {
    use BelongsToTenant;
 protected $fillable = ['tenant_id','name','level','min_salary','max_salary']; protected function casts(): array { return ['min_salary'=>'decimal:2','max_salary'=>'decimal:2']; } }
