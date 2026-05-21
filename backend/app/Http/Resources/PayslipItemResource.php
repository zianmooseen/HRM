<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'salary_component_id' => $this->salary_component_id,
            'label' => $this->label,
            'type' => $this->type,
            'amount' => $this->amount,
            'metadata' => $this->metadata_json,
        ];
    }
}
