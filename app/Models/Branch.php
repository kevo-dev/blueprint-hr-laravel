<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Branch extends Model { protected $fillable = ['tenant_id','name','code','location']; public function departments(){return $this->hasMany(Department::class);} public function tenant(){return $this->belongsTo(Tenant::class);} }
