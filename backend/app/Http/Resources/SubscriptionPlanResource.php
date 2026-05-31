<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'billing_cycle' => $this->billing_cycle,
            'price' => $this->price,
            'currency' => $this->currency,
            'max_employees' => $this->max_employees,
            'features' => $this->features_json ?? [],
            'status' => $this->status,
        ];
    }
}
