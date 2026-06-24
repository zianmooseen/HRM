<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class VerifyWpsTransferProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:verified,rejected,needs_review'],
            'notes' => ['nullable', 'required_if:status,rejected,needs_review', 'string', 'max:2000'],
        ];
    }
}
