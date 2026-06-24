<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMohreEstablishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->currentCompany()?->id;
        $establishmentId = $this->route('establishment')?->id;

        return [
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'establishment_name' => ['required', 'string', 'max:255'],
            'mohre_establishment_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('mohre_establishments')->where('company_id', $companyId)->ignore($establishmentId),
            ],
            'labour_file_number' => ['nullable', 'string', 'max:100'],
            'establishment_card_number' => ['nullable', 'string', 'max:100'],
            'trade_license_number' => ['nullable', 'string', 'max:100'],
            'emirate' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive,expired,under_review'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'wps_required' => ['required', 'boolean'],
            'wps_exemption_reason' => ['nullable', 'required_if:wps_required,false', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
