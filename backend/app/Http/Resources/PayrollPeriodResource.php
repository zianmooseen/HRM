<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mohre_establishment_id' => $this->mohre_establishment_id,
            'wps_provider_id' => $this->wps_provider_id,
            'period_start' => optional($this->period_start)->toDateString(),
            'period_end' => optional($this->period_end)->toDateString(),
            'pay_date' => optional($this->pay_date)->toDateString(),
            'payroll_due_date' => optional($this->payroll_due_date)->toDateString(),
            'status' => $this->status,
            'wps_status' => $this->wps_status,
            'approved_by' => $this->approved_by,
            'approved_at' => optional($this->approved_at)->toIso8601String(),
            'locked_at' => optional($this->locked_at)->toIso8601String(),
            'payslips_count' => $this->whenCounted('payslips'),
        ];
    }
}
