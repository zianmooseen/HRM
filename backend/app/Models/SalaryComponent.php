<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'is_taxable',
        'is_recurring',
        'status',
    ];

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_recurring' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employeeSalaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }
}
