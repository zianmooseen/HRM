<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeOnboardingTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_onboarding_case_id' => $this->employee_onboarding_case_id,
            'employee_id' => $this->employee_id,
            'title' => $this->title,
            'description' => $this->description,
            'task_type' => $this->task_type,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'assigned_to_role' => $this->assigned_to_role,
            'required' => $this->required,
            'status' => $this->status,
            'due_date' => optional($this->due_date)->toDateString(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'completed_by' => $this->completed_by,
        ];
    }
}
