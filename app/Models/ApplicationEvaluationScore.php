<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One criterion rating (1–5) on a scorecard. Immutable once the scorecard is submitted.
 */
class ApplicationEvaluationScore extends Model
{
    protected $fillable = [
        'application_evaluation_id',
        'evaluation_criterion_id',
        'criterion_name',
        'rating',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'application_evaluation_id' => 'integer',
            'evaluation_criterion_id' => 'integer',
            'rating' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (self $score) {
            if (ApplicationEvaluation::whereKey($score->application_evaluation_id)->value('status') === ApplicationEvaluation::STATUS_SUBMITTED) {
                throw new LogicException('Scores on a submitted evaluation are immutable.');
            }
        };
        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ApplicationEvaluation::class, 'application_evaluation_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'evaluation_criterion_id');
    }
}
