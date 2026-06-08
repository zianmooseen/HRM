<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWpsPayrollBatchStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:draft,generated,submitted,processing,accepted,partially_accepted,rejected,corrected,cancelled'],
            'rejection_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:1000'],
            'bank_reference' => ['nullable', 'string', 'max:255'],
            'response_filename' => ['nullable', 'string', 'max:255'],
            'response_details' => ['nullable', 'array'],
        ];
    }
}
