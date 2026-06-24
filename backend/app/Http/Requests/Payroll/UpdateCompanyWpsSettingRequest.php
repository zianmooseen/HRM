<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyWpsSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mohre_establishment_id' => ['required', 'integer', 'exists:mohre_establishments,id'],
            'wps_provider_id' => ['required', 'integer', 'exists:wps_providers,id'],
            'payroll_due_day' => ['required', 'integer', 'between:1,28'],
            'salary_period_type' => ['required', 'in:monthly,weekly,biweekly,custom'],
            'payment_currency' => ['required', 'string', 'size:3'],
            'sif_export_enabled' => ['required', 'boolean'],
            'provider_portal_url' => ['nullable', 'url', 'max:500'],
            'provider_customer_reference' => ['nullable', 'string', 'max:255'],
            'auto_mark_paid_allowed' => ['required', 'boolean'],
            'agent_code' => ['nullable', 'string', 'max:100'],
            'sender_id' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
