<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A job offer to a selected applicant. Status changes only through OfferService
 * and OfferApprovalService:
 *
 *   draft → pending_approval → approved → issued → accepted | declined | expired
 *   pending_approval → rejected (in approval)
 *   draft | pending_approval | approved | issued → withdrawn
 *
 * Terms are editable only in draft; rejected, accepted, declined, expired and
 * withdrawn offers never change. Proposed compensation only: no employee or
 * payroll record is created from an offer.
 */
class JobOffer extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_ISSUED,
        self::STATUS_ACCEPTED, self::STATUS_DECLINED, self::STATUS_EXPIRED, self::STATUS_WITHDRAWN,
    ];

    /** At most one per application (database-enforced); an accepted offer stays the application's offer. */
    public const ACTIVE = [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_ISSUED, self::STATUS_ACCEPTED];

    /** Still in play (can be withdrawn). */
    public const OPEN = [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_ISSUED];

    public const FINAL = [self::STATUS_REJECTED, self::STATUS_ACCEPTED, self::STATUS_DECLINED, self::STATUS_EXPIRED, self::STATUS_WITHDRAWN];

    public const RESPONSE_ACCEPTED = 'accepted';

    public const RESPONSE_DECLINED = 'declined';

    public const FREQUENCIES = ['hourly', 'daily', 'weekly', 'semi_monthly', 'monthly', 'annually'];

    /** Columns that make up the offer's terms. */
    public const TERMS = [
        'job_title_id', 'position_title', 'department_id', 'department_name', 'employment_status_id', 'employment_type',
        'location_id', 'work_location', 'proposed_start_date', 'expiry_date', 'base_salary', 'salary_frequency',
        'currency', 'benefits', 'remarks',
    ];

    protected $fillable = [
        'offer_number',
        'application_id',
        'vacancy_id',
        'applicant_id',
        'application_selection_id',
        'status',
        'job_title_id',
        'position_title',
        'department_id',
        'department_name',
        'employment_status_id',
        'employment_type',
        'location_id',
        'work_location',
        'proposed_start_date',
        'expiry_date',
        'base_salary',
        'salary_frequency',
        'currency',
        'benefits',
        'remarks',
        'offer_date',
        'created_by',
        'updated_by',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'rejected_at',
        'issued_at',
        'issued_by',
        'response',
        'responded_at',
        'response_recorded_by',
        'response_remarks',
        'expired_at',
        'withdrawn_at',
        'withdrawn_by',
        'withdrawal_reason',
    ];

    protected $hidden = ['active_application_id'];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'vacancy_id' => 'integer',
            'applicant_id' => 'integer',
            'application_selection_id' => 'integer',
            'job_title_id' => 'integer',
            'department_id' => 'integer',
            'employment_status_id' => 'integer',
            'location_id' => 'integer',
            'proposed_start_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
            'offer_date' => 'date:Y-m-d',
            'base_salary' => 'decimal:2',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'submitted_at' => 'datetime',
            'submitted_by' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'issued_at' => 'datetime',
            'issued_by' => 'integer',
            'responded_at' => 'datetime',
            'response_recorded_by' => 'integer',
            'expired_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'withdrawn_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $offer) {
            $from = $offer->getOriginal('status');

            if (in_array($from, self::FINAL, true)) {
                throw new LogicException("A {$from} offer is immutable.");
            }
            if ($from !== self::STATUS_DRAFT && $offer->isDirty(self::TERMS)) {
                throw new LogicException('Offer terms can only change while the offer is a draft.');
            }
        });
        static::deleting(fn () => throw new LogicException('Offers are never deleted; withdraw them instead.'));
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function selection(): BelongsTo
    {
        return $this->belongsTo(ApplicationSelection::class, 'application_selection_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(JobOfferApproval::class)->orderBy('approval_order');
    }

    public function events(): HasMany
    {
        return $this->hasMany(JobOfferEvent::class)->orderBy('id');
    }

    public function currentApproval(): ?JobOfferApproval
    {
        return $this->status === self::STATUS_PENDING
            ? $this->approvals()->where('status', JobOfferApproval::STATUS_PENDING)->orderBy('approval_order')->first()
            : null;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** Past its expiry date (the offer can be accepted on the expiry date itself). */
    public function isPastExpiry(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }
}
