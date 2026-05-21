<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalRuleSetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_code' => $this->country_code,
            'jurisdiction' => $this->jurisdiction,
            'name' => $this->name,
            'version' => $this->version,
            'effective_from' => optional($this->effective_from)->toDateString(),
            'effective_to' => optional($this->effective_to)->toDateString(),
            'status' => $this->status,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'rule_key' => $item->rule_key,
                'rule_type' => $item->rule_type,
                'value' => $item->value_json['value'] ?? null,
                'description' => $item->description,
            ])->values()),
        ];
    }
}
