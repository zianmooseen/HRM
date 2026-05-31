<?php

namespace App\Http\Requests\Compliance;

use Illuminate\Foundation\Http\FormRequest;

class ImportPublicHolidaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'holidays' => ['required', 'array', 'min:1', 'max:100'],
            'holidays.*.name' => ['required', 'string', 'max:255'],
            'holidays.*.holiday_date' => ['required', 'date'],
            'holidays.*.country_code' => ['nullable', 'string', 'size:2'],
            'holidays.*.emirate' => ['nullable', 'string', 'max:255'],
            'holidays.*.paid' => ['required', 'boolean'],
            'holidays.*.source' => ['required', 'in:company,government,imported,manual'],
            'holidays.*.status' => ['required', 'in:active,inactive'],
        ];
    }
}
