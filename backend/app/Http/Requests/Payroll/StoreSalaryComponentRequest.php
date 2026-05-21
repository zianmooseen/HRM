<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:earning,deduction'],
            'is_taxable' => ['boolean'],
            'is_recurring' => ['boolean'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
