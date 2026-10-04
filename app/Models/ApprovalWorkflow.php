<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable, module-keyed approval chain (modules "job_requisition", "job_offer").
 */
class ApprovalWorkflow extends Model
{
    public const MODULE_JOB_REQUISITION = 'job_requisition';

    public const MODULE_JOB_OFFER = 'job_offer';

    protected $fillable = [
        'module',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class)->orderBy('step_order');
    }
}
