<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Configurable assessment type (Recruitment → Settings). result_type decides how a
 * result is recorded: a score out of a maximum, or pass/fail.
 */
class AssessmentType extends Model
{
    public const RESULT_SCORE = 'score';

    public const RESULT_PASS_FAIL = 'pass_fail';

    public const RESULT_TYPES = [self::RESULT_SCORE, self::RESULT_PASS_FAIL];

    protected $fillable = [
        'code',
        'name',
        'description',
        'result_type',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
