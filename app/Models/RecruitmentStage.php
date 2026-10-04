<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The configurable pipeline template. Vacancies copy it (vacancy_stages) when
 * they open; editing it never changes an existing vacancy's pipeline.
 */
class RecruitmentStage extends Model
{
    public const TYPE_APPLIED = 'applied';

    public const TYPE_SCREENING = 'screening';

    public const TYPE_SHORTLISTED = 'shortlisted';

    public const TYPE_INTERVIEW = 'interview';

    public const TYPE_ASSESSMENT = 'assessment';

    public const TYPE_EVALUATION = 'evaluation';

    /** Stage types this phase understands; later phases add offer and hired. */
    public const TYPES = [self::TYPE_APPLIED, self::TYPE_SCREENING, self::TYPE_SHORTLISTED, self::TYPE_INTERVIEW, self::TYPE_ASSESSMENT, self::TYPE_EVALUATION];

    /** Stages reached after the shortlist (the application keeps status "shortlisted"). */
    public const POST_SHORTLIST_TYPES = [self::TYPE_INTERVIEW, self::TYPE_ASSESSMENT, self::TYPE_EVALUATION];

    protected $fillable = [
        'code',
        'name',
        'stage_type',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
