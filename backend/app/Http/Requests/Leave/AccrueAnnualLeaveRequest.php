<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class AccrueAnnualLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'accrual_date' => ['nullable', 'date'],
        ];
    }
}
