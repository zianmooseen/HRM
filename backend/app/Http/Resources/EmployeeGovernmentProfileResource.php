<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeGovernmentProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'mohre_establishment_id' => $this->mohre_establishment_id,
            'labour_card_number' => $this->labour_card_number,
            'work_permit_number' => $this->work_permit_number,
            'person_code' => $this->person_code,
            'emirates_id_number' => $this->emirates_id_number,
            'visa_file_number' => $this->visa_file_number,
            'passport_number' => $this->passport_number,
            'wps_employee_identifier' => $this->wps_employee_identifier,
        ];
    }
}
