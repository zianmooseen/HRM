<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLeaveBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'leave_type_id' => $this->leave_type_id,
            'leave_year' => $this->leave_year,
            'opening_balance' => $this->opening_balance,
            'accrued_days' => $this->accrued_days,
            'used_days' => $this->used_days,
            'pending_days' => $this->pending_days,
            'carried_forward_days' => $this->carried_forward_days,
            'encashed_days' => $this->encashed_days,
            'adjusted_days' => $this->adjusted_days,
            'closing_balance' => $this->closing_balance,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'leave_type' => $this->whenLoaded('leaveType', fn () => new LeaveTypeResource($this->leaveType)),
        ];
    }
}
