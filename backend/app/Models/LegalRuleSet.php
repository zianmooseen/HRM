<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalRuleSet extends Model
{
    protected $fillable = [
        'country_code',
        'jurisdiction',
        'name',
        'version',
        'effective_from',
        'effective_to',
        'source_reference',
        'status',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(LegalRuleItem::class);
    }
}
