<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;
    protected $fillable = ['company_name', 'kra_pin', 'email', 'phone', 'address', 'subdomain', 'status', 'currency', 'timezone', 'logo_path'];
    public function users() { return $this->hasMany(User::class); }
    public function employees() { return $this->hasMany(Employee::class); }
    public function branches() { return $this->hasMany(Branch::class); }
}
