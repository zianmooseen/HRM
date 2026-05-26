<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOnboardingTask extends Model
{
    protected $fillable = [
        'company_id',
        'employee_onboarding_case_id',
        'employee_id',
        'title',
        'description',
        'task_type',
        'assigned_to_user_id',
        'assigned_to_role',
        'required',
        'status',
        'due_date',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'required' => 'boolean',
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(EmployeeOnboardingCase::class, 'employee_onboarding_case_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
