<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'code',
        'billing_cycle',
        'price',
        'currency',
        'max_employees',
        'features_json',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features_json' => 'array',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }
}
