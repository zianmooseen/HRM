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
            'batch_number' => $this->batch_number,
            'status' => $this->status,
            'file_format' => $this->file_format,
            'salary_month' => $this->salary_month,
            'record_count' => $this->record_count,
            'total_amount' => $this->total_amount,
            'generated_at' => optional($this->generated_at)->toIso8601String(),
            'submitted_at' => optional($this->submitted_at)->toIso8601String(),
            'accepted_at' => optional($this->accepted_at)->toIso8601String(),
            'rejected_at' => optional($this->rejected_at)->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'validation_errors' => $this->validation_errors_json,
            'payroll_period' => $this->whenLoaded('payrollPeriod', fn () => new PayrollPeriodResource($this->payrollPeriod)),
            'items' => $this->whenLoaded('items', fn () => WpsPayrollBatchItemResource::collection($this->items)),
        ];
    }
}
