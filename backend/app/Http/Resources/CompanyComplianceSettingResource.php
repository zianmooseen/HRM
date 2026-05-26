<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyComplianceSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'legal_rule_set_id' => $this->legal_rule_set_id,
            'payroll_day_divisor' => $this->payroll_day_divisor,
            'annual_leave_accrual_method' => $this->annual_leave_accrual_method,
            'annual_leave_carry_forward_allowed' => $this->annual_leave_carry_forward_allowed,
            'annual_leave_max_carry_forward_days' => $this->annual_leave_max_carry_forward_days,
            'public_holidays_count_as_annual_leave' => $this->public_holidays_count_as_annual_leave,
            'sick_leave_requires_medical_certificate' => $this->sick_leave_requires_medical_certificate,
            'sick_leave_notification_days' => $this->sick_leave_notification_days,
            'emiratisation_monitoring_enabled' => $this->emiratisation_monitoring_enabled,
            'legal_rule_set' => $this->whenLoaded('legalRuleSet', fn () => new LegalRuleSetResource($this->legalRuleSet)),
        ];
    }
}
