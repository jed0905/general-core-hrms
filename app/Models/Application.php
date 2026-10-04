<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One applicant's application to one vacancy. current_vacancy_stage_id and
 * status change only through ApplicationPipelineService.
 */
class Application extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SHORTLISTED = 'shortlisted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_SHORTLISTED, self::STATUS_REJECTED, self::STATUS_WITHDRAWN];

    /** No further pipeline actions in this phase. */
    public const TERMINAL = [self::STATUS_REJECTED, self::STATUS_WITHDRAWN];

    protected $fillable = [
        'application_number',
        'applicant_id',
        'vacancy_id',
        'current_vacancy_stage_id',
        'status',
        'recruitment_source_id',
        'applied_at',
        'last_activity_at',
        'shortlisted_at',
        'shortlisted_by',
        'rejection_reason_id',
        'rejection_remarks',
        'rejected_at',
        'rejected_by',
        'withdrawal_reason',
        'withdrawn_at',
        'withdrawn_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'applicant_id' => 'integer',
            'vacancy_id' => 'integer',
            'current_vacancy_stage_id' => 'integer',
            'recruitment_source_id' => 'integer',
            'shortlisted_by' => 'integer',
            'rejection_reason_id' => 'integer',
            'rejected_by' => 'integer',
            'withdrawn_by' => 'integer',
            'created_by' => 'integer',
            'applied_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'shortlisted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(VacancyStage::class, 'current_vacancy_stage_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(RecruitmentSource::class, 'recruitment_source_id');
    }

    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(RejectionReason::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(ApplicantDocument::class, 'application_documents')->withPivot('attached_by')->withTimestamps();
    }

    public function history(): HasMany
    {
        return $this->hasMany(ApplicationStageHistory::class)->orderBy('acted_at')->orderBy('id');
    }

    public function screening(): HasOne
    {
        return $this->hasOne(ApplicationScreening::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ApplicationNote::class)->latest('id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(ApplicationInterview::class)->orderBy('round');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ApplicationAssessment::class)->orderBy('id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(ApplicationEvaluation::class)->orderBy('id');
    }

    /** Selection decisions, oldest first; the last one is current. */
    public function selections(): HasMany
    {
        return $this->hasMany(ApplicationSelection::class)->orderBy('id');
    }

    public function currentSelection(): HasOne
    {
        return $this->hasOne(ApplicationSelection::class)->latestOfMany();
    }

    public function offers(): HasMany
    {
        return $this->hasMany(JobOffer::class)->orderBy('id');
    }

    /** The hand-off to Core HR, once the accepted offer has been converted. */
    public function conversion(): HasOne
    {
        return $this->hasOne(ApplicationConversion::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }
}
