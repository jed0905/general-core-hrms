<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A hiring opportunity. Position fields are the vacancy's own copy (taken from
 * the requisition at creation). Status changes only through VacancyService.
 */
class Vacancy extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_FILLED = 'filled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_OPEN, self::STATUS_ON_HOLD, self::STATUS_CLOSED, self::STATUS_FILLED, self::STATUS_CANCELLED];

    public const TERMINAL = [self::STATUS_CLOSED, self::STATUS_FILLED, self::STATUS_CANCELLED];

    /**
     * Allowed status changes: [from => [to, ...]].
     */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_OPEN, self::STATUS_CANCELLED],
        self::STATUS_OPEN => [self::STATUS_ON_HOLD, self::STATUS_CLOSED, self::STATUS_FILLED, self::STATUS_CANCELLED],
        self::STATUS_ON_HOLD => [self::STATUS_OPEN, self::STATUS_CLOSED, self::STATUS_CANCELLED],
        self::STATUS_CLOSED => [],
        self::STATUS_FILLED => [],
        self::STATUS_CANCELLED => [],
    ];

    public const VISIBILITIES = ['internal', 'external', 'both'];

    protected $fillable = [
        'vacancy_number',
        'job_requisition_id',
        'title',
        'job_title_id',
        'department_id',
        'location_id',
        'employment_status_id',
        'openings',
        'filled_count',
        'description',
        'responsibilities',
        'qualifications',
        'salary_min',
        'salary_max',
        'salary_currency',
        'opening_date',
        'closing_date',
        'visibility',
        'hiring_manager_id',
        'status',
        'status_reason',
        'opened_at',
        'closed_at',
        'created_by',
        'public_slug',
        'published_at',
        'published_by',
        'show_salary_publicly',
    ];

    /** Audiences that may see a vacancy on the public careers site. */
    public const PUBLIC_VISIBILITIES = ['external', 'both'];

    protected $appends = ['remaining_openings'];

    protected function casts(): array
    {
        return [
            'job_requisition_id' => 'integer',
            'job_title_id' => 'integer',
            'department_id' => 'integer',
            'location_id' => 'integer',
            'employment_status_id' => 'integer',
            'openings' => 'integer',
            'filled_count' => 'integer',
            'hiring_manager_id' => 'integer',
            'created_by' => 'integer',
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'opening_date' => 'date:Y-m-d',
            'closing_date' => 'date:Y-m-d',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'published_at' => 'datetime',
            'published_by' => 'integer',
            'show_salary_publicly' => 'boolean',
        ];
    }

    public function getRemainingOpeningsAttribute(): int
    {
        return max(0, (int) $this->openings - (int) $this->filled_count);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hiring_manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** This vacancy's own pipeline (copied from the stage template when it opens). */
    public function stages(): HasMany
    {
        return $this->hasMany(VacancyStage::class)->orderBy('sort_order');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * On the careers site: open, meant for external candidates, published, and
     * not past its closing date (applications are accepted through that day).
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('vacancies.status', self::STATUS_OPEN)
            ->whereIn('vacancies.visibility', self::PUBLIC_VISIBILITIES)
            ->whereNotNull('vacancies.published_at')
            ->whereNotNull('vacancies.public_slug')
            ->where(fn ($q) => $q->whereNull('vacancies.closing_date')->orWhereDate('vacancies.closing_date', '>=', today()));
    }

    public function isPubliclyOpen(): bool
    {
        return $this->status === self::STATUS_OPEN
            && in_array($this->visibility, self::PUBLIC_VISIBILITIES, true)
            && $this->published_at !== null
            && $this->public_slug !== null
            && ($this->closing_date === null || ! $this->closing_date->lt(today()));
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }
}
