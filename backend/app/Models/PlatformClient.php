<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformClient extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['database_name', 'setup_payload'];

    protected $casts = ['setup_payload' => 'encrypted:array', 'ready_at' => 'datetime', 'maintenance_at' => 'datetime', 'session_version' => 'integer'];

    public function getConnectionName()
    {
        return config('tenancy.central_connection');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TenantProvisioningRun::class);
    }
}
