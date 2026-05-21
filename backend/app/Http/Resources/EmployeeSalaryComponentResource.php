<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSalaryComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'salary_component_id' => $this->salary_component_id,
            'amount' => $this->amount,
            'effective_from' => optional($this->effective_from)->toDateString(),
            'effective_to' => optional($this->effective_to)->toDateString(),
            'status' => $this->status,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'salary_component' => $this->whenLoaded('salaryComponent', fn () => new SalaryComponentResource($this->salaryComponent)),
        ];
    }
}
