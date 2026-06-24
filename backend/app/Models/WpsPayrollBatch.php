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
        'mohre_establishment_id',
        'wps_provider_id',
        'batch_number',
        'status',
        'file_format',
        'provider',
        'salary_month',
        'payroll_due_date',
        'record_count',
        'total_amount',
        'generated_at',
        'submitted_at',
        'accepted_at',
        'paid_at',
        'rejected_at',
        'rejection_reason',
        'failure_reason',
        'manual_override_reason',
        'proof_status',
        'bank_reference',
        'provider_reference',
        'response_filename',
        'response_details_json',
        'generated_by',
        'status_updated_by',
        'file_content',
        'file_hash',
        'validation_errors_json',
    ];

    protected $casts = [
        'record_count' => 'integer',
        'total_amount' => 'decimal:2',
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'accepted_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
        'payroll_due_date' => 'date',
        'response_details_json' => 'array',
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

    public function mohreEstablishment(): BelongsTo
    {
        return $this->belongsTo(MohreEstablishment::class);
    }

    public function wpsProvider(): BelongsTo
    {
        return $this->belongsTo(WpsProvider::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(WpsTransferProof::class);
    }
}
