<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class MarkEmployeeTerminationPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
