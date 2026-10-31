<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Attendance quantities of one employee for one payroll period, built from
 * attendance_days. Only approved summaries feed payroll. Once a finalized run
 * consumed it (consumed_run_id) the quantities never change; later corrections
 * only set changed_after_payroll so the difference is paid by adjustment.
 */
class AttendancePeriodSummary extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'employee_id', 'payroll_period_id', 'quantities', 'details', 'status', 'approved_by', 'approved_at',
        'consumed_run_id', 'changed_after_payroll', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantities' => 'array',
            'details' => 'array',
            'approved_at' => 'datetime',
            'changed_after_payroll' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (AttendancePeriodSummary $summary) {
            $changed = array_diff(array_keys($summary->getDirty()), ['changed_after_payroll', 'updated_at']);
            if ($summary->getOriginal('consumed_run_id') !== null && $changed !== []) {
                throw new LogicException('An attendance summary used by a finalized payroll run cannot change.');
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
