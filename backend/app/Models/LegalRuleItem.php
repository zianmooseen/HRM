<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalRuleItem extends Model
{
    protected $fillable = [
        'legal_rule_set_id',
        'rule_key',
        'rule_type',
        'value_json',
        'description',
        'source_reference',
    ];

    protected $casts = [
        'value_json' => 'array',
    ];

    public function legalRuleSet(): BelongsTo
    {
        return $this->belongsTo(LegalRuleSet::class);
    }
}
