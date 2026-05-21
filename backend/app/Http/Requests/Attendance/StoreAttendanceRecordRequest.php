<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'status' => ['required', 'in:present,absent,late,half_day,on_leave,holiday,remote'],
            'source' => ['required', 'in:manual,web,mobile,biometric,import'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
