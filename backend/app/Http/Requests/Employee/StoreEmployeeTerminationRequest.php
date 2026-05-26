<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeTerminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'termination_date' => ['required', 'date'],
            'last_working_date' => ['nullable', 'date', 'before_or_equal:termination_date'],
            'termination_type' => ['required', 'in:company_initiated,employee_resignation,mutual,contract_end,other'],
            'termination_reason' => ['nullable', 'string', 'max:1000'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'unpaid_leave_days' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'leave_encashment_amount' => ['nullable', 'numeric', 'min:0'],
            'notice_paid_amount' => ['nullable', 'numeric', 'min:0'],
            'other_earnings_amount' => ['nullable', 'numeric', 'min:0'],
            'deductions_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
