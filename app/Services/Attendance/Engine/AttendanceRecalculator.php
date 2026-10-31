<?php

namespace App\Services\Attendance\Engine;

use App\Models\AttendanceDay;
use App\Models\AttendancePeriodSummary;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Recomputes attendance days after new events or decisions. Each touched date
 * also recomputes the previous date, whose overnight-shift window may include
 * early-morning events. Summaries already consumed by finalized payroll are
 * flagged changed_after_payroll instead of being rewritten.
 */
class AttendanceRecalculator
{
    public function __construct(protected AttendanceCalculator $calculator) {}

    /** @param  array<int, array<string, true>>  $touched  employee id => [date => true] */
    public function recalculateTouched(array $touched): void
    {
        foreach ($touched as $employeeId => $dates) {
            $employee = Employee::find($employeeId);
            if (! $employee) {
                continue;
            }

            $all = [];
            foreach (array_keys($dates) as $date) {
                $all[$date] = true;
                $all[CarbonImmutable::parse($date)->subDay()->toDateString()] = true;
            }
            ksort($all);

            $changed = [];
            foreach (array_keys($all) as $date) {
                if ($this->changed($this->calculator->compute($employee, $date))) {
                    $changed[] = $date;
                }
            }
            if ($changed) {
                $this->flagConsumedSummaries($employeeId, $changed);
            }
        }
    }

    /** @param  array<int, int>  $employeeIds */
    public function recalculateRange(array $employeeIds, string $from, string $to): int
    {
        $count = 0;
        foreach (Employee::whereIn('id', $employeeIds)->get() as $employee) {
            $changed = [];
            foreach (CarbonPeriod::create($from, $to) as $date) {
                if ($this->changed($this->calculator->compute($employee, $date->toDateString()))) {
                    $changed[] = $date->toDateString();
                }
                $count++;
            }
            if ($changed) {
                $this->flagConsumedSummaries($employee->id, $changed);
            }
        }

        return $count;
    }

    /** True when a recomputation changed what payroll could consume. */
    private function changed(?AttendanceDay $day): bool
    {
        return $day !== null && ($day->wasRecentlyCreated || $day->wasChanged([
            ...AttendanceDay::MINUTE_COLUMNS, 'leave_days', 'absence_days', 'status', 'buckets', 'day_type',
        ]));
    }

    private function flagConsumedSummaries(int $employeeId, array $dates): void
    {
        $from = min($dates);
        $to = max($dates);

        AttendancePeriodSummary::where('employee_id', $employeeId)
            ->whereNotNull('consumed_run_id')
            ->where('changed_after_payroll', false)
            ->whereHas('period', fn ($q) => $q->where('period_start', '<=', $to)->where('period_end', '>=', $from))
            ->get()
            ->each(fn ($s) => $s->update(['changed_after_payroll' => true]));
    }
}
