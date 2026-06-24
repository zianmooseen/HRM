<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WpsPayrollBatchItem extends Model
{
    protected $fillable = [
        'wps_payroll_batch_id',
        'payslip_id',
        'employee_id',
        'employee_code',
        'employee_name',
        'bank_iban',
        'bank_routing_code',
        'wps_person_id',
        'provider_employee_reference',
        'provider_transaction_reference',
        'fixed_income',
        'variable_income',
        'net_pay',
        'days_in_period',
        'row_payload_json',
        'status',
        'paid_at',
        'failure_reason',
    ];

    protected $casts = [
        'fixed_income' => 'decimal:2',
        'variable_income' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'days_in_period' => 'integer',
        'row_payload_json' => 'array',
        'bank_iban' => 'encrypted',
        'paid_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WpsPayrollBatch::class, 'wps_payroll_batch_id');
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
