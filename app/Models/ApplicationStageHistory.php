<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable audit trail of an application's stage and status changes.
 * Rows are only ever inserted (by ApplicationPipelineService).
 */
class ApplicationStageHistory extends Model
{
    public const ACTION_APPLIED = 'applied';

    public const ACTION_MOVED = 'moved';

    public const ACTION_SHORTLISTED = 'shortlisted';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'application_id',
        'action',
        'from_vacancy_stage_id',
        'to_vacancy_stage_id',
        'from_stage_name',
        'to_stage_name',
        'from_status',
        'to_status',
        'rejection_reason_id',
        'remarks',
        'acted_by',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'from_vacancy_stage_id' => 'integer',
            'to_vacancy_stage_id' => 'integer',
            'rejection_reason_id' => 'integer',
            'acted_by' => 'integer',
            'acted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Application stage history is immutable.'));
        static::deleting(fn () => throw new LogicException('Application stage history is immutable.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(RejectionReason::class);
    }
}
