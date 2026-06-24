<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeGovernmentProfile extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'mohre_establishment_id',
        'labour_card_number',
        'work_permit_number',
        'person_code',
        'emirates_id_number',
        'visa_file_number',
        'passport_number',
        'wps_employee_identifier',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'labour_card_number' => 'encrypted',
        'work_permit_number' => 'encrypted',
        'person_code' => 'encrypted',
        'emirates_id_number' => 'encrypted',
        'visa_file_number' => 'encrypted',
        'passport_number' => 'encrypted',
        'wps_employee_identifier' => 'encrypted',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(MohreEstablishment::class, 'mohre_establishment_id');
    }
}
