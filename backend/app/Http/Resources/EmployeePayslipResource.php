<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeePayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period_start' => $this->payrollPeriod->period_start?->toDateString(),
            'period_end' => $this->payrollPeriod->period_end?->toDateString(),
            'pay_date' => $this->payrollPeriod->pay_date?->toDateString(),
            'gross_pay' => $this->gross_pay, 'total_deductions' => $this->total_deductions, 'net_pay' => $this->net_pay,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id, 'label' => $item->label, 'type' => $item->type, 'amount' => $item->amount,
            ])),
        ];
    }
}
