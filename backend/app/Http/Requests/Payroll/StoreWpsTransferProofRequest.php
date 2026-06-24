<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StoreWpsTransferProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proof_type' => ['required', 'in:provider_receipt,bank_confirmation,exchange_house_receipt,wps_report,manual_reference,api_confirmation'],
            'provider_reference' => ['nullable', 'required_without_all:transaction_reference,file', 'string', 'max:255'],
            'transaction_reference' => ['nullable', 'required_without_all:provider_reference,file', 'string', 'max:255'],
            'file' => ['nullable', 'required_without_all:provider_reference,transaction_reference', 'file', 'mimes:pdf,jpg,jpeg,png,webp,csv,xls,xlsx', 'max:20480'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
