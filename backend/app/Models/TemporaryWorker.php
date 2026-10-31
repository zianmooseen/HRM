<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemporaryWorker extends Model
{
    protected $fillable = ['company_id', 'branch_id', 'name', 'phone', 'job_description', 'wage_basis', 'rate', 'currency', 'status'];

    protected $casts = ['rate' => 'decimal:2'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TemporaryWorkerEntry::class);
    }
}
