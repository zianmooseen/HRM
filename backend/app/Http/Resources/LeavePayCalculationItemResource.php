<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeavePayCalculationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'leave_request_id' => $this->leave_request_id,
            'pay_tier' => $this->pay_tier,
            'days' => $this->days,
            'pay_percentage' => $this->pay_percentage,
            'daily_wage' => $this->daily_wage,
            'gross_pay_amount' => $this->gross_pay_amount,
            'deduction_amount' => $this->deduction_amount,
            'calculation_basis' => $this->calculation_basis,
            'rule_snapshot' => $this->rule_snapshot_json,
        ];
    }
}
