<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'trade_license_number' => $this->trade_license_number,
            'tax_registration_number' => $this->tax_registration_number,
            'country' => $this->country,
            'emirate' => $this->emirate,
            'default_currency' => $this->default_currency,
            'timezone' => $this->timezone,
            'status' => $this->status,
            'emiratisation_applicable' => $this->emiratisation_applicable,
            'emiratisation_category' => $this->emiratisation_category,
            'economic_sector_code' => $this->economic_sector_code,
            'mohre_establishment_number' => $this->mohre_establishment_number,
            'wps_bank_name' => $this->wps_bank_name,
            'wps_provider' => $this->wps_provider ?? 'generic',
            'wps_agent_code' => $this->wps_agent_code,
            'wps_file_sender_id' => $this->wps_file_sender_id,
        ];
    }
}
