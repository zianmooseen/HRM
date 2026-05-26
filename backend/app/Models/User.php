<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['company_id', 'branch_id', 'department_id', 'scope'])
            ->withTimestamps();
    }

    public function scopedCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_roles')
            ->withPivot(['role_id', 'branch_id', 'department_id', 'scope'])
            ->withTimestamps();
    }

    public function employeeRecord(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains('slug', $role);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->contains('slug', $permission);
    }

    public function currentCompany(): ?Company
    {
        if ($this->relationLoaded('scopedCompanies')) {
            return $this->scopedCompanies->first();
        }

        return $this->scopedCompanies()->first();
    }
}
