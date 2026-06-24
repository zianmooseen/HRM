<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WpsPayrollBatchItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payslip_id' => $this->payslip_id,
            'employee_id' => $this->employee_id,
            'employee_code' => $this->employee_code,
            'employee_name' => $this->employee_name,
            'bank_iban' => $this->bank_iban,
            'bank_routing_code' => $this->bank_routing_code,
            'wps_person_id' => $this->wps_person_id,
            'provider_employee_reference' => $this->provider_employee_reference,
            'provider_transaction_reference' => $this->provider_transaction_reference,
            'fixed_income' => $this->fixed_income,
            'variable_income' => $this->variable_income,
            'net_pay' => $this->net_pay,
            'days_in_period' => $this->days_in_period,
            'row_payload' => $this->row_payload_json,
            'status' => $this->status,
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'failure_reason' => $this->failure_reason,
        ];
    }
}
