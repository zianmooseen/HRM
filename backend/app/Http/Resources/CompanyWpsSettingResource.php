<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyWpsSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'mohre_establishment_id' => $this->mohre_establishment_id,
            'wps_provider_id' => $this->wps_provider_id,
            'payroll_due_day' => $this->payroll_due_day,
            'salary_period_type' => $this->salary_period_type,
            'payment_currency' => $this->payment_currency,
            'sif_export_enabled' => $this->sif_export_enabled,
            'provider_portal_url' => $this->provider_portal_url,
            'provider_customer_reference' => $this->provider_customer_reference,
            'auto_mark_paid_allowed' => $this->auto_mark_paid_allowed,
            'agent_code' => $this->agent_code,
            'sender_id' => $this->sender_id,
            'status' => $this->status,
            'establishment' => $this->whenLoaded('establishment', fn () => new MohreEstablishmentResource($this->establishment)),
            'provider' => $this->whenLoaded('provider', fn () => new WpsProviderResource($this->provider)),
        ];
    }
}
