<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryWorkerEntry extends Model
{
    protected $fillable = ['company_id', 'temporary_worker_id', 'entry_key', 'kind', 'date', 'wage_basis', 'quantity', 'rate', 'amount', 'method', 'reference', 'notes', 'created_by', 'voided_at', 'void_reason'];

    protected $casts = ['date' => 'date:Y-m-d', 'quantity' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2', 'voided_at' => 'datetime'];

    public function temporaryWorker(): BelongsTo
    {
        return $this->belongsTo(TemporaryWorker::class);
    }
}
