<?php

namespace App\Http\Requests\Compliance;

use Illuminate\Foundation\Http\FormRequest;

class CalculateGratuityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'termination_date' => ['required', 'date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'unpaid_leave_days' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
