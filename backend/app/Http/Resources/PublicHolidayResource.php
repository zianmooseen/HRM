<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicHolidayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'holiday_date' => $this->holiday_date?->toDateString(),
            'country_code' => $this->country_code,
            'emirate' => $this->emirate,
            'paid' => $this->paid,
            'source' => $this->source,
            'status' => $this->status,
        ];
    }
}
