<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A panelist's scorecard for one interview of an application (at most one per
 * application + interview + evaluator, enforced by a unique index). Drafts are
 * editable by their owner; a submitted scorecard can never change or be deleted.
 * The recommendation is advisory only: it never selects or rejects anyone.
 */
class ApplicationEvaluation extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const RECOMMEND = 'recommend';

    public const NEUTRAL = 'neutral';

    public const DO_NOT_RECOMMEND = 'do_not_recommend';

    public const RECOMMENDATIONS = [self::RECOMMEND, self::NEUTRAL, self::DO_NOT_RECOMMEND];

    /** Default 1–5 scale labels. */
    public const RATINGS = [
        1 => 'Poor',
        2 => 'Needs Improvement',
        3 => 'Meets Expectations',
        4 => 'Very Good',
        5 => 'Excellent',
    ];

    protected $fillable = [
        'application_id',
        'application_interview_id',
        'evaluator_employee_id',
        'evaluator_user_id',
        'status',
        'recommendation',
        'comments',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'application_interview_id' => 'integer',
            'evaluator_employee_id' => 'integer',
            'evaluator_user_id' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (self $evaluation) {
            if ($evaluation->getOriginal('status') === self::STATUS_SUBMITTED) {
                throw new LogicException('A submitted evaluation is immutable.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(ApplicationInterview::class, 'application_interview_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'evaluator_employee_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ApplicationEvaluationScore::class)->orderBy('id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }
}
