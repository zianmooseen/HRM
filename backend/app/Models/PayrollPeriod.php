<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'company_id',
        'mohre_establishment_id',
        'wps_provider_id',
        'period_start',
        'period_end',
        'pay_date',
        'payroll_due_date',
        'status',
        'wps_status',
        'created_by',
        'approved_by',
        'approved_at',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'pay_date' => 'date',
        'payroll_due_date' => 'date',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function wpsPayrollBatches(): HasMany
    {
        return $this->hasMany(WpsPayrollBatch::class);
    }

    public function mohreEstablishment(): BelongsTo
    {
        return $this->belongsTo(MohreEstablishment::class);
    }

    public function wpsProvider(): BelongsTo
    {
        return $this->belongsTo(WpsProvider::class);
    }
}
