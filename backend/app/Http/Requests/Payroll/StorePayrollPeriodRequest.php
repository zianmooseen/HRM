<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'pay_date' => ['nullable', 'date', 'after_or_equal:period_end'],
            'mohre_establishment_id' => ['nullable', 'integer', 'exists:mohre_establishments,id'],
            'wps_provider_id' => ['nullable', 'integer', 'exists:wps_providers,id'],
            'payroll_due_date' => ['nullable', 'date', 'after_or_equal:period_end'],
        ];
    }
}
