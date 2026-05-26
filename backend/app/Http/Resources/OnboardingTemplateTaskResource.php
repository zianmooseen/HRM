<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingTemplateTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'task_type' => $this->task_type,
            'assigned_to_role' => $this->assigned_to_role,
            'required' => $this->required,
            'sort_order' => $this->sort_order,
            'due_days_after_start' => $this->due_days_after_start,
        ];
    }
}
