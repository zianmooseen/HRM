<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WpsTransferProofResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wps_payroll_batch_id' => $this->wps_payroll_batch_id,
            'payroll_period_id' => $this->payroll_period_id,
            'wps_provider_id' => $this->wps_provider_id,
            'proof_type' => $this->proof_type,
            'provider_reference' => $this->provider_reference,
            'transaction_reference' => $this->transaction_reference,
            'original_file_name' => $this->original_file_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'proof_file_hash' => $this->proof_file_hash,
            'status' => $this->status,
            'notes' => $this->notes,
            'verified_by' => $this->verified_by,
            'verified_at' => optional($this->verified_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
