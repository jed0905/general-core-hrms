<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A time quantity for one employee in one payroll period. Core categories are
 * below; premium categories are declared by the payroll country's localization.
 * Only approved inputs are paid.
 */
class PayrollTimeInput extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const DAYS_WORKED = 'days_worked';

    public const HOURS_WORKED = 'hours_worked';

    public const ABSENCE_DAYS = 'absence_days';

    public const LATE_HOURS = 'late_hours';

    public const UNDERTIME_HOURS = 'undertime_hours';

    /** category => unit */
    public const CORE_CATEGORIES = [
        self::DAYS_WORKED => 'days',
        self::HOURS_WORKED => 'hours',
        self::ABSENCE_DAYS => 'days',
        self::LATE_HOURS => 'hours',
        self::UNDERTIME_HOURS => 'hours',
    ];

    protected $fillable = [
        'payroll_period_id', 'employee_id', 'category', 'quantity', 'source', 'remarks', 'status',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'approved_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
