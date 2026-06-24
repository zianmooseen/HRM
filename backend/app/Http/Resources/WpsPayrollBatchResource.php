<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WpsPayrollBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'payroll_period_id' => $this->payroll_period_id,
            'mohre_establishment_id' => $this->mohre_establishment_id,
            'wps_provider_id' => $this->wps_provider_id,
            'batch_number' => $this->batch_number,
            'status' => $this->status,
            'file_format' => $this->file_format,
            'provider' => $this->provider,
            'salary_month' => $this->salary_month,
            'payroll_due_date' => optional($this->payroll_due_date)->toDateString(),
            'record_count' => $this->record_count,
            'total_amount' => $this->total_amount,
            'generated_at' => optional($this->generated_at)->toIso8601String(),
            'submitted_at' => optional($this->submitted_at)->toIso8601String(),
            'accepted_at' => optional($this->accepted_at)->toIso8601String(),
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'rejected_at' => optional($this->rejected_at)->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'failure_reason' => $this->failure_reason,
            'manual_override_reason' => $this->manual_override_reason,
            'proof_status' => $this->proof_status,
            'bank_reference' => $this->bank_reference,
            'provider_reference' => $this->provider_reference,
            'file_hash' => $this->file_hash,
            'response_filename' => $this->response_filename,
            'response_details' => $this->response_details_json,
            'validation_errors' => $this->validation_errors_json,
            'payroll_period' => $this->whenLoaded('payrollPeriod', fn () => new PayrollPeriodResource($this->payrollPeriod)),
            'items' => $this->whenLoaded('items', fn () => WpsPayrollBatchItemResource::collection($this->items)),
            'mohre_establishment' => $this->whenLoaded('mohreEstablishment', fn () => new MohreEstablishmentResource($this->mohreEstablishment)),
            'wps_provider' => $this->whenLoaded('wpsProvider', fn () => new WpsProviderResource($this->wpsProvider)),
            'proofs' => $this->whenLoaded('proofs', fn () => WpsTransferProofResource::collection($this->proofs)),
        ];
    }
}
