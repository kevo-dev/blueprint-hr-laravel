<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PayrollPeriod extends Model { protected $fillable = ['tenant_id','name','month','year','status']; public function transactions(){return $this->hasMany(PayrollTransaction::class);} public function runs(){return $this->hasMany(PayrollRun::class);} }
