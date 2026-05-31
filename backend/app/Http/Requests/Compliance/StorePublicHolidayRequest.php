<?php

namespace App\Http\Requests\Compliance;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'holiday_date' => ['required', 'date'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'emirate' => ['nullable', 'string', 'max:255'],
            'paid' => ['required', 'boolean'],
            'source' => ['required', 'in:company,government,imported,manual'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
