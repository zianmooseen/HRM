<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipItem extends Model
{
    protected $fillable = [
        'payslip_id',
        'salary_component_id',
        'label',
        'type',
        'amount',
        'metadata_json',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata_json' => 'array',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }
}
