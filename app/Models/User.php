<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'role' => Role::class,
        ];
    }

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function auditLogs() { return $this->hasMany(AuditLog::class); }

    public function hasRole(Role|string ...$roles): bool
    {
        $role = $this->role instanceof Role ? $this->role->value : (string) $this->role;
        return in_array($role, array_map(fn ($value) => $value instanceof Role ? $value->value : $value, $roles), true);
    }
}
