<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'job_title_id' => ['nullable', 'integer'],
            'employee_code' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'work_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'hire_date' => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'employment_type' => ['nullable', 'string', 'max:100'],
            'contract_type' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:draft,onboarding,active,on_leave,suspended,terminated,archived'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
