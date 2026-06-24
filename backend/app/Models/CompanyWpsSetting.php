<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyWpsSetting extends Model
{
    protected $fillable = [
        'company_id',
        'mohre_establishment_id',
        'wps_provider_id',
        'payroll_due_day',
        'salary_period_type',
        'payment_currency',
        'sif_export_enabled',
        'provider_portal_url',
        'provider_customer_reference',
        'auto_mark_paid_allowed',
        'agent_code',
        'sender_id',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'payroll_due_day' => 'integer',
        'sif_export_enabled' => 'boolean',
        'auto_mark_paid_allowed' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(MohreEstablishment::class, 'mohre_establishment_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(WpsProvider::class, 'wps_provider_id');
    }
}
