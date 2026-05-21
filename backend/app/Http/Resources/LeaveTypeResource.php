<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'paid_type' => $this->paid_type,
            'requires_approval' => $this->requires_approval,
            'requires_document' => $this->requires_document,
            'is_statutory' => $this->is_statutory,
            'status' => $this->status,
        ];
    }
}
