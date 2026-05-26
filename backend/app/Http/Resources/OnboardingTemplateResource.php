<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'description' => $this->description,
            'employment_type' => $this->employment_type,
            'is_default' => $this->is_default,
            'status' => $this->status,
            'tasks' => $this->whenLoaded('tasks', fn () => OnboardingTemplateTaskResource::collection($this->tasks)),
        ];
    }
}
