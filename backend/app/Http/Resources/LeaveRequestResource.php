<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'leave_type_id' => $this->leave_type_id,
            'start_date' => optional($this->start_date)->toDateString(),
            'end_date' => optional($this->end_date)->toDateString(),
            'total_days' => $this->total_days,
            'working_days' => $this->working_days,
            'status' => $this->status,
            'reason' => $this->reason,
            'medical_certificate_document_id' => $this->medical_certificate_document_id,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'approved_at' => optional($this->approved_at)->toIso8601String(),
            'approval_note' => $this->approval_note,
            'rejected_by' => $this->rejected_by,
            'rejected_at' => optional($this->rejected_at)->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'leave_type' => $this->whenLoaded('leaveType', fn () => new LeaveTypeResource($this->leaveType)),
            'pay_calculation_items' => $this->whenLoaded('payCalculationItems', fn () => LeavePayCalculationItemResource::collection($this->payCalculationItems)),
            'status_events' => $this->whenLoaded('statusEvents', fn () => LeaveRequestStatusEventResource::collection($this->statusEvents)),
        ];
    }
}
