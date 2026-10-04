<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit trail of an offer: created, edited, submitted, approved,
 * rejected (in approval), issued, accepted / declined (candidate), expired, withdrawn,
 * converted (hand-off to Core HR).
 */
class JobOfferEvent extends Model
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const SUBMITTED = 'submitted';

    public const STEP_APPROVED = 'step_approved';

    public const APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'approval_rejected';

    public const ISSUED = 'issued';

    public const CANDIDATE_ACCEPTED = 'candidate_accepted';

    public const CANDIDATE_DECLINED = 'candidate_declined';

    public const EXPIRED = 'expired';

    public const WITHDRAWN = 'withdrawn';

    /** The accepted offer was handed to Core HR (status stays accepted). */
    public const CONVERTED = 'converted';

    protected $fillable = [
        'job_offer_id',
        'event',
        'from_status',
        'to_status',
        'actor_id',
        'remarks',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'job_offer_id' => 'integer',
            'actor_id' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Offer history is immutable.'));
        static::deleting(fn () => throw new LogicException('Offer history is immutable.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
