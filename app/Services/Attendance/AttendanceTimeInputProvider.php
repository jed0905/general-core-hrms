<?php

namespace App\Services\Attendance;

use App\Models\AttendancePeriodSummary;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\Payroll\Contracts\TimeInputProvider;
use App\Services\Payroll\Contracts\ValidatesTimeInputs;
use App\Services\Payroll\Inputs\ApprovedTimeInputProvider;

/**
 * Payroll's time source. Pay groups with time_source "manual_inputs" (default)
 * keep the original behavior: approved payroll_time_inputs only. Pay groups
 * with "attendance" read the APPROVED attendance summary for the period; an
 * approved manual input for the same category overrides it (explicit payroll
 * correction). Payroll never recalculates attendance.
 */
class AttendanceTimeInputProvider implements TimeInputProvider, ValidatesTimeInputs
{
    public function __construct(protected ApprovedTimeInputProvider $manual) {}

    public function quantities(Employee $employee, PayrollPeriod $period): array
    {
        $manual = $this->manual->quantities($employee, $period);
        if (! $this->usesAttendance($period)) {
            return $manual;
        }

        $summary = $this->summary($employee, $period);

        return array_merge($summary?->quantities ?? [], $manual);
    }

    public function problems(Employee $employee, PayrollPeriod $period): array
    {
        if (! $this->usesAttendance($period) || $this->summary($employee, $period)) {
            return [];
        }

        return ['The pay group takes time from attendance, but no approved attendance summary exists for this employee and period. Generate and approve it in Attendance > Payroll Summaries.'];
    }

    private function usesAttendance(PayrollPeriod $period): bool
    {
        return $period->payGroup()->value('time_source') === 'attendance';
    }

    private function summary(Employee $employee, PayrollPeriod $period): ?AttendancePeriodSummary
    {
        return AttendancePeriodSummary::where('employee_id', $employee->id)
            ->where('payroll_period_id', $period->id)
            ->where('status', AttendancePeriodSummary::STATUS_APPROVED)
            ->first();
    }
}
