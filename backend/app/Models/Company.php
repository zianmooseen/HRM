<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'trade_license_number',
        'tax_registration_number',
        'country',
        'emirate',
        'default_currency',
        'timezone',
        'status',
        'emiratisation_applicable',
        'emiratisation_category',
        'economic_sector_code',
        'mohre_establishment_number',
    ];

    protected $casts = [
        'emiratisation_applicable' => 'boolean',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function jobTitles(): HasMany
    {
        return $this->hasMany(JobTitle::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
