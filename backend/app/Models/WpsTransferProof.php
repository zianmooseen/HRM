<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WpsTransferProof extends Model
{
    protected $fillable = [
        'company_id',
        'wps_payroll_batch_id',
        'payroll_period_id',
        'wps_provider_id',
        'proof_type',
        'provider_reference',
        'transaction_reference',
        'disk',
        'path',
        'original_file_name',
        'mime_type',
        'size_bytes',
        'proof_file_hash',
        'uploaded_by',
        'verified_by',
        'verified_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WpsPayrollBatch::class, 'wps_payroll_batch_id');
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(WpsProvider::class, 'wps_provider_id');
    }
}
