<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One interview of an application (an application can have many). Status and
 * times change only through InterviewService; reschedules are logged in
 * interview_reschedules, and cancelled/completed interviews stay on record.
 */
class ApplicationInterview extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_SCHEDULED, self::STATUS_COMPLETED, self::STATUS_CANCELLED];

    public const MODE_IN_PERSON = 'in_person';

    public const MODE_VIDEO = 'video';

    public const MODE_PHONE = 'phone';

    public const MODES = [self::MODE_IN_PERSON, self::MODE_VIDEO, self::MODE_PHONE];

    protected $fillable = [
        'application_id',
        'interview_type_id',
        'round',
        'mode',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'location',
        'meeting_url',
        'instructions',
        'status',
        'completed_at',
        'completed_by',
        'completion_remarks',
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
            'interview_type_id' => 'integer',
            'round' => 'integer',
            'duration_minutes' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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
        return $this->belongsTo(InterviewType::class, 'interview_type_id');
    }

    public function panelists(): HasMany
    {
        return $this->hasMany(InterviewPanelist::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function panelistEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'interview_panelists')->withPivot('is_primary')->withTimestamps();
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(InterviewReschedule::class)->orderBy('rescheduled_at')->orderBy('id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(ApplicationEvaluation::class);
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function hasPanelist(?int $employeeId): bool
    {
        return $employeeId !== null && InterviewPanelist::where('application_interview_id', $this->id)->where('employee_id', $employeeId)->exists();
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }
}
