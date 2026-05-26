<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestStatusEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'actor_user_id' => $this->actor_user_id,
            'note' => $this->note,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
