<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MohreEstablishmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'establishment_name' => $this->establishment_name,
            'mohre_establishment_number' => $this->mohre_establishment_number,
            'labour_file_number' => $this->labour_file_number,
            'establishment_card_number' => $this->establishment_card_number,
            'trade_license_number' => $this->trade_license_number,
            'emirate' => $this->emirate,
            'status' => $this->status,
            'issue_date' => optional($this->issue_date)->toDateString(),
            'expiry_date' => optional($this->expiry_date)->toDateString(),
            'wps_required' => $this->wps_required,
            'wps_exemption_reason' => $this->wps_exemption_reason,
            'notes' => $this->notes,
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
            'wps_setting' => $this->whenLoaded('wpsSetting', fn () => new CompanyWpsSettingResource($this->wpsSetting)),
        ];
    }
}
