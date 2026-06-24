<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WpsProvider extends Model
{
    protected $fillable = [
        'name',
        'code',
        'provider_type',
        'website',
        'contact_phone',
        'contact_email',
        'integration_type',
        'export_profile',
        'status',
    ];

    public function companySettings(): HasMany
    {
        return $this->hasMany(CompanyWpsSetting::class);
    }
}
