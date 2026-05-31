<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmiratisationRule extends Model
{
    protected $fillable = [
        'legal_rule_set_id',
        'category',
        'min_employee_count',
        'max_employee_count',
        'sector_codes_json',
        'annual_growth_percent',
        'semi_annual_growth_percent',
        'required_uae_citizens',
        'contribution_amount_per_missing_citizen',
        'contribution_frequency',
        'effective_from',
        'effective_to',
        'status',
    ];

    protected $casts = [
        'sector_codes_json' => 'array',
        'annual_growth_percent' => 'decimal:2',
        'semi_annual_growth_percent' => 'decimal:2',
        'contribution_amount_per_missing_citizen' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function legalRuleSet(): BelongsTo
    {
        return $this->belongsTo(LegalRuleSet::class);
    }
}
