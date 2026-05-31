<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanySubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'subscription_plan_id' => $this->subscription_plan_id,
            'status' => $this->status,
            'starts_on' => optional($this->starts_on)->toDateString(),
            'trial_ends_on' => optional($this->trial_ends_on)->toDateString(),
            'current_period_starts_on' => optional($this->current_period_starts_on)->toDateString(),
            'current_period_ends_on' => optional($this->current_period_ends_on)->toDateString(),
            'cancelled_on' => optional($this->cancelled_on)->toDateString(),
            'notes' => $this->notes,
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'plan' => $this->whenLoaded('plan', fn () => new SubscriptionPlanResource($this->plan)),
        ];
    }
}
