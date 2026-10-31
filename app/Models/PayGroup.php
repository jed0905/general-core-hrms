<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Employees of one legal entity paid together: frequency, pay currency, payroll
 * country (selects the localization) and working-time basis (rate divisors).
 */
class PayGroup extends Model
{
    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_BIWEEKLY = 'biweekly';

    public const FREQUENCY_SEMI_MONTHLY = 'semi_monthly';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCIES = [self::FREQUENCY_WEEKLY, self::FREQUENCY_BIWEEKLY, self::FREQUENCY_SEMI_MONTHLY, self::FREQUENCY_MONTHLY];

    /** Pay periods per year for each frequency. */
    public const PERIODS_PER_YEAR = [
        self::FREQUENCY_WEEKLY => 52,
        self::FREQUENCY_BIWEEKLY => 26,
        self::FREQUENCY_SEMI_MONTHLY => 24,
        self::FREQUENCY_MONTHLY => 12,
    ];

    public const PRORATION_METHODS = ['calendar_days', 'working_days'];

    public const ROUNDING_MODES = ['half_up', 'half_even', 'up', 'down'];

    /** Where payroll reads time quantities: approved manual inputs (default) or approved attendance summaries. */
    public const TIME_SOURCES = ['manual_inputs', 'attendance'];

    protected $fillable = [
        'legal_entity_id', 'code', 'name', 'payroll_country_code', 'pay_frequency', 'pay_currency',
        'hours_per_day', 'annual_working_days', 'proration_method', 'rounding_mode', 'time_source', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hours_per_day' => 'decimal:4',
            'annual_working_days' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(PayGroupCalendar::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(PayrollPeriod::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmploymentAssignment::class);
    }

    public function periodsPerYear(): int
    {
        return self::PERIODS_PER_YEAR[$this->pay_frequency];
    }
}
