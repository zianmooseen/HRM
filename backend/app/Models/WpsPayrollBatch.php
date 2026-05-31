<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WpsPayrollBatch extends Model
{
    protected $fillable = [
        'company_id',
        'payroll_period_id',
        'batch_number',
        'status',
        'file_format',
        'salary_month',
        'record_count',
        'total_amount',
        'generated_at',
        'submitted_at',
        'accepted_at',
        'rejected_at',
        'rejection_reason',
        'generated_by',
        'file_content',
        'validation_errors_json',
    ];

    protected $casts = [
        'record_count' => 'integer',
        'total_amount' => 'decimal:2',
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'validation_errors_json' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WpsPayrollBatchItem::class);
    }
}
