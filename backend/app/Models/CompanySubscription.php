<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanySubscription extends Model
{
    protected $fillable = [
        'company_id',
        'subscription_plan_id',
        'status',
        'starts_on',
        'trial_ends_on',
        'current_period_starts_on',
        'current_period_ends_on',
        'cancelled_on',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'trial_ends_on' => 'date',
        'current_period_starts_on' => 'date',
        'current_period_ends_on' => 'date',
        'cancelled_on' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }
}
