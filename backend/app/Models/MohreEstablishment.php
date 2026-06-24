<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MohreEstablishment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'establishment_name',
        'mohre_establishment_number',
        'labour_file_number',
        'establishment_card_number',
        'trade_license_number',
        'emirate',
        'status',
        'issue_date',
        'expiry_date',
        'wps_required',
        'wps_exemption_reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'wps_required' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function wpsSetting(): HasOne
    {
        return $this->hasOne(CompanyWpsSetting::class);
    }

    public function employeeGovernmentProfiles(): HasMany
    {
        return $this->hasMany(EmployeeGovernmentProfile::class);
    }
}
