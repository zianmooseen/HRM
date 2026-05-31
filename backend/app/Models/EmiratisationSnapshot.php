<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmiratisationSnapshot extends Model
{
    protected $fillable = [
        'company_id',
        'snapshot_date',
        'total_active_workers',
        'total_skilled_workers',
        'total_active_uae_citizens',
        'total_skilled_uae_citizens',
        'required_uae_citizens',
        'missing_uae_citizens',
        'estimated_contribution_amount',
        'compliance_status',
        'rule_snapshot_json',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'estimated_contribution_amount' => 'decimal:2',
        'rule_snapshot_json' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
