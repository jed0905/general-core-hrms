<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One selection decision (append-only). The latest row of an application is its
 * current decision; a change of mind is a new row. Written only by
 * SelectionService, together with the vacancy's filled_count.
 */
class ApplicationSelection extends Model
{
    public const SELECTED = 'selected';

    public const NOT_SELECTED = 'not_selected';

    public const DECISIONS = [self::SELECTED, self::NOT_SELECTED];

    protected $fillable = [
        'application_id',
        'vacancy_id',
        'applicant_id',
        'decision',
        'is_automatic',
        'remarks',
        'completed_interviews',
        'submitted_evaluations',
        'recommend_count',
        'neutral_count',
        'do_not_recommend_count',
        'average_rating',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'vacancy_id' => 'integer',
            'applicant_id' => 'integer',
            'is_automatic' => 'boolean',
            'completed_interviews' => 'integer',
            'submitted_evaluations' => 'integer',
            'recommend_count' => 'integer',
            'neutral_count' => 'integer',
            'do_not_recommend_count' => 'integer',
            'average_rating' => 'decimal:2',
            'decided_by' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Selection decisions are immutable; record a new decision instead.'));
        static::deleting(fn () => throw new LogicException('Selection decisions are immutable; record a new decision instead.'));
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isSelected(): bool
    {
        return $this->decision === self::SELECTED;
    }
}
