<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'trade_license_number' => ['nullable', 'string', 'max:255'],
            'tax_registration_number' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'emirate' => ['nullable', 'string', 'max:255'],
            'default_currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'emiratisation_applicable' => ['boolean'],
            'emiratisation_category' => ['required', 'in:large_50_plus,selected_20_to_49,not_applicable'],
            'economic_sector_code' => ['nullable', 'string', 'max:100'],
            'mohre_establishment_number' => ['nullable', 'string', 'max:100'],
            'wps_bank_name' => ['nullable', 'string', 'max:255'],
            'wps_provider' => ['nullable', 'in:generic,fab,emirates_nbd'],
            'wps_agent_code' => ['nullable', 'string', 'max:100'],
            'wps_file_sender_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
