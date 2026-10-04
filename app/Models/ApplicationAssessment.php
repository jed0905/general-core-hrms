<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An assessment of an application (many per application). Status changes only
 * through AssessmentService: scheduled → in_progress → completed, or cancelled
 * from either open state. Completed and cancelled are final.
 */
class ApplicationAssessment extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_SCHEDULED, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED];

    public const OPEN = [self::STATUS_SCHEDULED, self::STATUS_IN_PROGRESS];

    protected $fillable = [
        'application_id',
        'assessment_type_id',
        'result_type',
        'scheduled_at',
        'status',
        'score',
        'maximum_score',
        'passed',
        'assessor_employee_id',
        'remarks',
        'started_at',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'assessment_type_id' => 'integer',
            'scheduled_at' => 'datetime',
            'score' => 'decimal:2',
            'maximum_score' => 'decimal:2',
            'passed' => 'boolean',
            'assessor_employee_id' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'completed_by' => 'integer',
            'cancelled_at' => 'datetime',
            'cancelled_by' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class, 'assessment_type_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assessor_employee_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
