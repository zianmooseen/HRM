<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeOnboardingCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tasks = $this->whenLoaded('tasks', fn () => $this->tasks);
        $totalTasks = $this->relationLoaded('tasks') ? $this->tasks->count() : null;
        $completedTasks = $this->relationLoaded('tasks') ? $this->tasks->where('status', 'completed')->count() : null;

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'onboarding_template_id' => $this->onboarding_template_id,
            'status' => $this->status,
            'started_at' => optional($this->started_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'cancelled_at' => optional($this->cancelled_at)->toIso8601String(),
            'progress' => $totalTasks === null ? null : [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'percent' => $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0,
            ],
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'template' => $this->whenLoaded('template', fn () => new OnboardingTemplateResource($this->template)),
            'tasks' => $tasks instanceof \Illuminate\Support\MissingValue ? $tasks : EmployeeOnboardingTaskResource::collection($tasks),
        ];
    }
}
