<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewSalary = $request->user()?->hasPermission('employees.view_salary') ?? false;

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'job_title_id' => $this->job_title_id,
            'employee_code' => $this->employee_code,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'personal_email' => $this->personal_email,
            'work_email' => $this->work_email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'hire_date' => optional($this->hire_date)->toDateString(),
            'probation_end_date' => optional($this->probation_end_date)->toDateString(),
            'contract_start_date' => optional($this->contract_start_date)->toDateString(),
            'contract_end_date' => optional($this->contract_end_date)->toDateString(),
            'employment_type' => $this->employment_type,
            'contract_type' => $this->contract_type,
            'status' => $this->status,
            'basic_salary' => $this->when($canViewSalary, $this->basic_salary),
            'monthly_salary' => $this->when($canViewSalary, $this->monthly_salary),
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
            'department' => $this->whenLoaded('department', fn () => new DepartmentResource($this->department)),
            'job_title' => $this->whenLoaded('jobTitle', fn () => new JobTitleResource($this->jobTitle)),
        ];
    }
}
