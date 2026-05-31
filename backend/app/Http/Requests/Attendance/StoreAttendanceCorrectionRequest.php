<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer'],
            'attendance_record_id' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
            'correction_type' => ['required', 'in:missed_check_in,missed_check_out,wrong_time,absence_status,other'],
            'requested_check_in' => ['nullable', 'date_format:H:i'],
            'requested_check_out' => ['nullable', 'date_format:H:i', 'after:requested_check_in'],
            'requested_break_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'requested_status' => ['required', 'in:present,absent,late,half_day,on_leave,holiday,remote'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
