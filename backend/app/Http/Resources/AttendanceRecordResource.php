<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'date' => optional($this->date)->toDateString(),
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'break_minutes' => $this->break_minutes,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'display_name' => $this->employee->display_name,
            ]),
        ];
    }
}
