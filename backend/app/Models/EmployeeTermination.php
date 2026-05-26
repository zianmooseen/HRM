<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTermination extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'termination_date',
        'last_working_date',
        'termination_type',
        'termination_reason',
        'basic_salary',
        'unpaid_leave_days',
        'gratuity_amount',
        'leave_encashment_amount',
        'notice_paid_amount',
        'other_earnings_amount',
        'deductions_amount',
        'final_settlement_amount',
        'paid_amount',
        'paid_at',
        'payment_reference',
        'status',
        'calculation_snapshot_json',
        'notes',
        'created_by',
        'paid_by',
    ];

    protected $casts = [
        'termination_date' => 'date',
        'last_working_date' => 'date',
        'basic_salary' => 'decimal:2',
        'unpaid_leave_days' => 'decimal:2',
        'gratuity_amount' => 'decimal:2',
        'leave_encashment_amount' => 'decimal:2',
        'notice_paid_amount' => 'decimal:2',
        'other_earnings_amount' => 'decimal:2',
        'deductions_amount' => 'decimal:2',
        'final_settlement_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'calculation_snapshot_json' => 'array',
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
