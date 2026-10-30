<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * One version of a statutory rule of one country (any country), with its
 * official source and verification state.
 *
 *   draft -> verified -> superseded      draft -> rejected
 *
 * Drafts may be edited (each edit is a new revision). Verified and superseded
 * versions are read-only except for closing effective_to and being superseded;
 * a change in law is a new version. Only verified versions may feed a run that
 * is finalized.
 */
class StatutoryRuleVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_VERIFIED, self::STATUS_SUPERSEDED, self::STATUS_REJECTED];

    /** Usable for calculation (drafts only in previews, see StatutoryRuleResolver). */
    public const USABLE = [self::STATUS_DRAFT, self::STATUS_VERIFIED, self::STATUS_SUPERSEDED];

    public const LOCKED = [self::STATUS_VERIFIED, self::STATUS_SUPERSEDED];

    public const CATEGORIES = [
        'income_tax', 'social_insurance', 'healthcare', 'pension', 'employment_insurance', 'statutory_deduction',
        'employer_contribution', 'minimum_wage', 'overtime', 'holiday_pay', 'rest_day_pay', 'night_work',
        'leave', 'rounding', 'reporting', 'retention',
    ];

    public const APPLIES_BY = ['pay_date', 'period_start', 'period_end', 'earned_date'];

    /** Columns that may still change on a locked version. */
    private const MUTABLE_WHEN_LOCKED = ['effective_to', 'verification_status', 'updated_at'];

    protected $fillable = [
        'country_code', 'jurisdiction_code', 'rule_key', 'variant', 'category', 'name', 'calculation_method',
        'applies_by', 'rounding_mode', 'effective_from', 'effective_to', 'version', 'revision', 'official_source',
        'source_url', 'issuance_reference', 'retrieved_at', 'verification_status', 'verified_by', 'verified_at',
        'verification_notes', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
            'retrieved_at' => 'date:Y-m-d',
            'verified_at' => 'datetime',
            'version' => 'integer',
            'revision' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (StatutoryRuleVersion $rule) {
            if (! in_array($rule->getOriginal('verification_status'), self::LOCKED, true)) {
                return;
            }

            if (array_diff(array_keys($rule->getDirty()), self::MUTABLE_WHEN_LOCKED) !== []
                || ! in_array($rule->verification_status, self::LOCKED, true)) {
                throw new LogicException('A verified statutory rule version is read-only; create a new version instead.');
            }
        });

        static::deleting(function (StatutoryRuleVersion $rule) {
            if (in_array($rule->getOriginal('verification_status'), self::LOCKED, true)
                || PayrollRunLine::where('statutory_rule_version_id', $rule->id)->exists()) {
                throw new LogicException('Statutory rule versions that are verified or used by payroll are kept.');
            }
        });
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(StatutoryRuleBracket::class)->orderBy('sequence');
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(StatutoryRuleParameter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isDraft(): bool
    {
        return $this->verification_status === self::STATUS_DRAFT;
    }

    public function isVerified(): bool
    {
        return in_array($this->verification_status, self::LOCKED, true);
    }

    /** Decimal string value of a parameter, or null. */
    public function parameter(string $key): ?string
    {
        $parameter = $this->parameters->firstWhere('key', $key);

        return $parameter?->value;
    }

    public function textParameter(string $key): ?string
    {
        return $this->parameters->firstWhere('key', $key)?->text_value;
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }
}
