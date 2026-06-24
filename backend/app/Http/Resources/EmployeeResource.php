<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewSalary = $request->user()?->hasPermission('employees.view_salary') ?? false;
        $canViewGovernmentIdentifiers = $request->user()?->hasPermission('mohre_establishments.view') ?? false;
        $governmentProfile = $this->relationLoaded('governmentProfile') ? $this->governmentProfile : null;
        $contractDaysRemaining = $this->contract_end_date
            ? now()->startOfDay()->diffInDays($this->contract_end_date, false)
            : null;

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'job_title_id' => $this->job_title_id,
            'manager_employee_id' => $this->manager_employee_id,
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
            'is_uae_citizen' => $this->is_uae_citizen,
            'skill_level' => $this->skill_level,
            'is_skilled_worker' => $this->is_skilled_worker,
            'work_permit_type' => $this->work_permit_type,
            'work_permit_number' => $this->when(
                $canViewGovernmentIdentifiers,
                $governmentProfile?->work_permit_number ?: $this->work_permit_number,
            ),
            'labor_card_number' => $this->when(
                $canViewGovernmentIdentifiers,
                $governmentProfile?->labour_card_number ?: $this->labor_card_number,
            ),
            'date_of_birth' => optional($this->date_of_birth)->toDateString(),
            'hire_date' => optional($this->hire_date)->toDateString(),
            'probation_end_date' => optional($this->probation_end_date)->toDateString(),
            'contract_start_date' => optional($this->contract_start_date)->toDateString(),
            'contract_end_date' => optional($this->contract_end_date)->toDateString(),
            'contract_days_remaining' => $contractDaysRemaining,
            'contract_expiry_status' => $this->contractExpiryStatus($contractDaysRemaining),
            'employment_type' => $this->employment_type,
            'contract_type' => $this->contract_type,
            'status' => $this->status,
            'basic_salary' => $this->when($canViewSalary, $this->basic_salary),
            'monthly_salary' => $this->when($canViewSalary, $this->monthly_salary),
            'bank_name' => $this->when($canViewSalary, $this->bank_name),
            'bank_iban' => $this->when($canViewSalary, $this->bank_iban),
            'bank_routing_code' => $this->when($canViewSalary, $this->bank_routing_code),
            'wps_person_id' => $this->when($canViewSalary, $this->wps_person_id),
            'government_profile' => $this->when(
                $canViewGovernmentIdentifiers && $this->relationLoaded('governmentProfile'),
                fn () => $this->governmentProfile
                    ? new EmployeeGovernmentProfileResource($this->governmentProfile)
                    : null,
            ),
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
            'department' => $this->whenLoaded('department', fn () => new DepartmentResource($this->department)),
            'job_title' => $this->whenLoaded('jobTitle', fn () => new JobTitleResource($this->jobTitle)),
            'manager' => $this->whenLoaded('manager', fn () => new self($this->manager)),
        ];
    }

    private function contractExpiryStatus(?int $daysRemaining): string
    {
        if ($daysRemaining === null) {
            return 'not_tracked';
        }

        if ($daysRemaining < 0) {
            return 'expired';
        }

        if ($daysRemaining <= 30) {
            return 'critical';
        }

        if ($daysRemaining <= 60) {
            return 'warning';
        }

        return 'valid';
    }
}
