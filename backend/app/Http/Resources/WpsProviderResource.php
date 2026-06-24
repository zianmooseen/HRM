<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WpsProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'provider_type' => $this->provider_type,
            'website' => $this->website,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'integration_type' => $this->integration_type,
            'export_profile' => $this->export_profile,
            'status' => $this->status,
        ];
    }
}
