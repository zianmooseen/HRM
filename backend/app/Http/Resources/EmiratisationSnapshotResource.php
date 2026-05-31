<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmiratisationSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'snapshot_date' => optional($this->snapshot_date)->toDateString(),
            'total_active_workers' => $this->total_active_workers,
            'total_skilled_workers' => $this->total_skilled_workers,
            'total_active_uae_citizens' => $this->total_active_uae_citizens,
            'total_skilled_uae_citizens' => $this->total_skilled_uae_citizens,
            'required_uae_citizens' => $this->required_uae_citizens,
            'missing_uae_citizens' => $this->missing_uae_citizens,
            'estimated_contribution_amount' => $this->estimated_contribution_amount,
            'compliance_status' => $this->compliance_status,
            'rule_snapshot' => $this->rule_snapshot_json,
        ];
    }
}
