<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class UpsertEmployeeLeaveBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'leave_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'accrued_days' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'carried_forward_days' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'adjusted_days' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'encashed_days' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
