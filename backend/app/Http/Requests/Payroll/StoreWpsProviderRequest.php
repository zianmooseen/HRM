<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWpsProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $providerId = $this->route('provider')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('wps_providers', 'code')->ignore($providerId)],
            'provider_type' => ['required', 'in:bank,exchange_house,financial_institution,digital_wallet,other'],
            'website' => ['nullable', 'url', 'max:500'],
            'contact_phone' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'integration_type' => ['required', 'in:manual_upload,file_export,api'],
            'export_profile' => ['required', 'in:generic,fab,emirates_nbd'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
