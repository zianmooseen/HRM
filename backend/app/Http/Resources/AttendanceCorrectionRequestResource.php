<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceCorrectionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'attendance_record_id' => $this->attendance_record_id,
            'date' => optional($this->date)->toDateString(),
            'correction_type' => $this->correction_type,
            'requested_check_in' => $this->requested_check_in,
            'requested_check_out' => $this->requested_check_out,
            'requested_break_minutes' => $this->requested_break_minutes,
            'requested_status' => $this->requested_status,
            'reason' => $this->reason,
            'status' => $this->status,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'approved_at' => optional($this->approved_at)->toIso8601String(),
            'rejected_by' => $this->rejected_by,
            'rejected_at' => optional($this->rejected_at)->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'attendance_record' => $this->whenLoaded('attendanceRecord', fn () => new AttendanceRecordResource($this->attendanceRecord)),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
