<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class StoreOnboardingTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'employment_type' => ['nullable', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:active,inactive'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.title' => ['required', 'string', 'max:255'],
            'tasks.*.description' => ['nullable', 'string', 'max:2000'],
            'tasks.*.task_type' => ['required', 'in:document_upload,hr_review,payroll_setup,account_creation,policy_acknowledgement,asset_assignment,training,custom'],
            'tasks.*.assigned_to_role' => ['nullable', 'string', 'max:100'],
            'tasks.*.required' => ['sometimes', 'boolean'],
            'tasks.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'tasks.*.due_days_after_start' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
