<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'client_code' => $this->client_code, 'company_id' => $this->company_id,
            'company_name' => $this->company?->name, 'provisioning_status' => $this->provisioning_status,
            'maintenance' => (bool) $this->maintenance_at,
            'ready_at' => $this->ready_at?->toIso8601String(),
            'latest_run' => $this->runs->first(),
        ];
    }
}
