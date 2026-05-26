<?php

namespace App\Http\Requests\Compliance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyComplianceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payroll_day_divisor' => ['required', 'in:calendar_30,actual_calendar_days,working_days'],
            'annual_leave_accrual_method' => ['required', 'in:monthly,annual,manual'],
            'annual_leave_carry_forward_allowed' => ['boolean'],
            'annual_leave_max_carry_forward_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'public_holidays_count_as_annual_leave' => ['boolean'],
            'sick_leave_requires_medical_certificate' => ['boolean'],
            'sick_leave_notification_days' => ['required', 'integer', 'min:0', 'max:30'],
            'emiratisation_monitoring_enabled' => ['boolean'],
        ];
    }
}
