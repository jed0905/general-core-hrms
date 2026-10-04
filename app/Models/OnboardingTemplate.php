<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable onboarding checklist. Applying it copies its tasks into an
 * onboarding (OnboardingService); editing it never changes existing onboardings.
 */
class OnboardingTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'employment_status_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'employment_status_id' => 'integer',
            'is_active' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(OnboardingTemplateTask::class)->orderBy('sort_order')->orderBy('id');
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
    }
}
