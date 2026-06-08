<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WpsComplianceAlert extends Model
{
    protected $fillable = [
        'company_id',
        'payroll_period_id',
        'type',
        'severity',
        'message',
        'due_date',
        'resolved_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'resolved_at' => 'datetime',
    ];
}
