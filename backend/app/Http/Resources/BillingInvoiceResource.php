<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company_subscription_id' => $this->company_subscription_id,
            'invoice_number' => $this->invoice_number,
            'issue_date' => optional($this->issue_date)->toDateString(),
            'due_date' => optional($this->due_date)->toDateString(),
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'notes' => $this->notes,
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'subscription' => $this->whenLoaded('subscription', fn () => new CompanySubscriptionResource($this->subscription)),
        ];
    }
}
