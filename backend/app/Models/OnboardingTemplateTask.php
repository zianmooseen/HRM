<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTemplateTask extends Model
{
    protected $fillable = [
        'onboarding_template_id',
        'title',
        'description',
        'task_type',
        'assigned_to_role',
        'required',
        'sort_order',
        'due_days_after_start',
    ];

    protected $casts = [
        'required' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }
}
