<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_period_id' => $this->payroll_period_id,
            'employee_id' => $this->employee_id,
            'gross_pay' => $this->gross_pay,
            'total_deductions' => $this->total_deductions,
            'net_pay' => $this->net_pay,
            'status' => $this->status,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'items' => $this->whenLoaded('items', fn () => PayslipItemResource::collection($this->items)),
        ];
    }
}
