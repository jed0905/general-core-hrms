<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * A payroll run for one pay group and period. Status changes only through
 * PayrollRunService:
 *
 *   draft -> calculated -> reviewed -> approved -> finalized
 *   calculated | reviewed | approved -> draft (returned)
 *   any non-finalized -> cancelled
 *
 * Types: regular (one active per period), supplemental (off-cycle, adjustments
 * only) and reversal (negates a finalized run). Finalized and cancelled runs
 * never change.
 */
class PayrollRun extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_CALCULATED, self::STATUS_REVIEWED,
        self::STATUS_APPROVED, self::STATUS_FINALIZED, self::STATUS_CANCELLED,
    ];

    public const TERMINAL = [self::STATUS_FINALIZED, self::STATUS_CANCELLED];

    /** Results exist and may be returned to draft for correction. */
    public const RETURNABLE = [self::STATUS_CALCULATED, self::STATUS_REVIEWED, self::STATUS_APPROVED];

    public const TYPE_REGULAR = 'regular';

    public const TYPE_SUPPLEMENTAL = 'supplemental';

    public const TYPE_REVERSAL = 'reversal';

    public const TYPES = [self::TYPE_REGULAR, self::TYPE_SUPPLEMENTAL, self::TYPE_REVERSAL];

    public const TOTAL_COLUMNS = [
        'gross_total', 'employee_deduction_total', 'tax_total', 'net_total',
        'employer_contribution_total', 'employer_cost_total',
    ];

    protected $fillable = [
        'run_number', 'legal_entity_id', 'pay_group_id', 'payroll_period_id', 'run_type', 'status', 'currency',
        'reverses_run_id', 'description', 'engine_version', 'uses_unverified_rules', ...self::TOTAL_COLUMNS,
        'employee_count', 'created_by', 'calculated_at', 'calculated_by', 'reviewed_at', 'reviewed_by',
        'approved_at', 'approved_by', 'finalized_at', 'finalized_by', 'cancelled_at', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'uses_unverified_rules' => 'boolean',
            'gross_total' => 'decimal:4',
            'employee_deduction_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'net_total' => 'decimal:4',
            'employer_contribution_total' => 'decimal:4',
            'employer_cost_total' => 'decimal:4',
            'employee_count' => 'integer',
            'calculated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PayrollRun $run) {
            if (in_array($run->getOriginal('status'), self::TERMINAL, true)) {
                throw new LogicException('Finalized or cancelled payroll runs cannot be changed.');
            }
        });

        static::deleting(function () {
            throw new LogicException('Payroll runs are never deleted; cancel or reverse them.');
        });
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function payGroup(): BelongsTo
    {
        return $this->belongsTo(PayGroup::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function reversesRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'reverses_run_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(PayrollRun::class, 'reverses_run_id')->where('status', '<>', self::STATUS_CANCELLED);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(PayrollRunEmployee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollRunLine::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(PayrollRunException::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PayrollRunEvent::class)->orderBy('id');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(PayrollDisbursement::class);
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    public function hasBlockingExceptions(): bool
    {
        return $this->exceptions()->where('severity', PayrollRunException::SEVERITY_ERROR)->exists();
    }
}
