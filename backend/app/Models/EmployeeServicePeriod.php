<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeServicePeriod extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'start_date',
        'end_date',
        'employment_type',
        'contract_type',
        'status',
        'change_reason',
        'created_by',
        'ended_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
