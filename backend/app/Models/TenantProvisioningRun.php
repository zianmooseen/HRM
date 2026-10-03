<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantProvisioningRun extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array'];

    public function getConnectionName()
    {
        return config('tenancy.central_connection');
    }
}
