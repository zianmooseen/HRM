<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyComplianceSetting extends Model
{
    protected $fillable = [
        'company_id',
        'legal_rule_set_id',
        'payroll_day_divisor',
        'annual_leave_accrual_method',
        'annual_leave_carry_forward_allowed',
        'annual_leave_max_carry_forward_days',
        'public_holidays_count_as_annual_leave',
        'sick_leave_requires_medical_certificate',
        'sick_leave_notification_days',
        'emiratisation_monitoring_enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'annual_leave_carry_forward_allowed' => 'boolean',
        'annual_leave_max_carry_forward_days' => 'decimal:2',
        'public_holidays_count_as_annual_leave' => 'boolean',
        'sick_leave_requires_medical_certificate' => 'boolean',
        'emiratisation_monitoring_enabled' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function legalRuleSet(): BelongsTo
    {
        return $this->belongsTo(LegalRuleSet::class);
    }
}
