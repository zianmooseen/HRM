<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeTerminationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'termination_date' => optional($this->termination_date)->toDateString(),
            'last_working_date' => optional($this->last_working_date)->toDateString(),
            'termination_type' => $this->termination_type,
            'termination_reason' => $this->termination_reason,
            'basic_salary' => $this->basic_salary,
            'unpaid_leave_days' => $this->unpaid_leave_days,
            'gratuity_amount' => $this->gratuity_amount,
            'leave_encashment_amount' => $this->leave_encashment_amount,
            'notice_paid_amount' => $this->notice_paid_amount,
            'other_earnings_amount' => $this->other_earnings_amount,
            'deductions_amount' => $this->deductions_amount,
            'final_settlement_amount' => $this->final_settlement_amount,
            'paid_amount' => $this->paid_amount,
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'payment_reference' => $this->payment_reference,
            'status' => $this->status,
            'calculation_snapshot' => $this->calculation_snapshot_json,
            'notes' => $this->notes,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
        ];
    }
}
