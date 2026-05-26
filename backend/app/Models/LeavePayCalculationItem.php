<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeavePayCalculationItem extends Model
{
    protected $fillable = [
        'company_id',
        'leave_request_id',
        'employee_id',
        'payroll_period_id',
        'leave_type_id',
        'pay_tier',
        'days',
        'pay_percentage',
        'daily_wage',
        'gross_pay_amount',
        'deduction_amount',
        'calculation_basis',
        'rule_snapshot_json',
    ];

    protected $casts = [
        'days' => 'decimal:2',
        'pay_percentage' => 'decimal:2',
        'daily_wage' => 'decimal:2',
        'gross_pay_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'rule_snapshot_json' => 'array',
    ];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
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
