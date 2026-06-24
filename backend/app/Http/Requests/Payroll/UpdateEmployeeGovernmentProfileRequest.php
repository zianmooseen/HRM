<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeGovernmentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mohre_establishment_id' => ['nullable', 'integer', 'exists:mohre_establishments,id'],
            'labour_card_number' => ['nullable', 'string', 'max:100'],
            'work_permit_number' => ['nullable', 'string', 'max:100'],
            'person_code' => ['nullable', 'string', 'max:100'],
            'emirates_id_number' => ['nullable', 'string', 'max:100'],
            'visa_file_number' => ['nullable', 'string', 'max:100'],
            'passport_number' => ['nullable', 'string', 'max:100'],
            'wps_employee_identifier' => ['nullable', 'string', 'max:100'],
        ];
    }
}
