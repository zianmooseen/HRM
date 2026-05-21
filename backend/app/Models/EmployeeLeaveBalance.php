<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveBalance extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'leave_year',
        'opening_balance',
        'accrued_days',
        'used_days',
        'pending_days',
        'carried_forward_days',
        'encashed_days',
        'adjusted_days',
        'closing_balance',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'accrued_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'pending_days' => 'decimal:2',
        'carried_forward_days' => 'decimal:2',
        'encashed_days' => 'decimal:2',
        'adjusted_days' => 'decimal:2',
        'closing_balance' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
