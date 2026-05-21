<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'category',
        'paid_type',
        'requires_approval',
        'requires_document',
        'is_statutory',
        'status',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'requires_document' => 'boolean',
        'is_statutory' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
