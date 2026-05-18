<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'company_id',
        'actor_user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'before_json',
        'after_json',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
    ];
}
