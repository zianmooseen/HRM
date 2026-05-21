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
            'period_start' => optional($this->period_start)->toDateString(),
            'period_end' => optional($this->period_end)->toDateString(),
            'pay_date' => optional($this->pay_date)->toDateString(),
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'approved_at' => optional($this->approved_at)->toIso8601String(),
            'payslips_count' => $this->whenCounted('payslips'),
        ];
    }
}
