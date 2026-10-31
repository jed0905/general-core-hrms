<?php

namespace App\Services\Attendance\Engine;

use App\Models\AttendancePolicy;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\EmploymentAssignment;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationDate;
use App\Models\Shift;
use App\Services\EmployeeWorkScheduleService;
use App\Services\HolidayService;
use Carbon\CarbonImmutable;

/**
 * Everything the calculator needs to know about one employee's work date:
 * timezone and policy (from the legal entity of the employee's payroll
 * assignment, else the default policy), jurisdiction (work country /
 * subdivision), the scheduled shift (roster override, else the Time module's
 * weekly schedule via EmployeeWorkScheduleService::resolveDay), the
 * jurisdiction-matched holiday and approved leave.
 */
class DayContextResolver
{
    public function __construct(
        protected EmployeeWorkScheduleService $schedules,
        protected HolidayService $holidays,
    ) {}

    public function resolve(Employee $employee, string $date): DayContext
    {
        $assignment = EmploymentAssignment::with('legalEntity:id,timezone')
            ->where('employee_id', $employee->id)
            ->overlapping($date, $date)
            ->orderByDesc('is_primary')
            ->orderByDesc('effective_from')
            ->first();

        $policy = ($assignment ? AttendancePolicy::where('legal_entity_id', $assignment->legal_entity_id)->where('is_active', true)->first() : null)
            ?? AttendancePolicy::where('is_default', true)->first()
            ?? new AttendancePolicy(['name' => 'Built-in neutral policy', 'pairing_mode' => 'in_out', 'debounce_minutes' => 1, 'deduct_scheduled_break' => true, 'overtime_requires_approval' => true, 'window_before_minutes' => 240, 'day_start_time' => '00:00:00']);

        $timezone = $assignment?->legalEntity?->timezone ?? $policy->timezone ?? config('app.timezone');
        [$shift, $isRestDay, $hasSchedule] = $this->schedule($employee, $date);

        return new DayContext(
            employee: $employee,
            date: $date,
            timezone: $timezone,
            policy: $policy,
            shift: $shift,
            isRestDay: $isRestDay,
            hasSchedule: $hasSchedule,
            holiday: $this->holiday($date, $assignment?->work_country_code, $assignment?->work_subdivision_code),
            leaveDays: $this->leaveDays($employee, $date, $shift),
        );
    }

    /** @return array{0: ?Shift, 1: bool, 2: bool} shift, rest day, has a schedule */
    protected function schedule(Employee $employee, string $date): array
    {
        $roster = EmployeeShiftAssignment::with('shift')->where('employee_id', $employee->id)->whereDate('work_date', $date)->first();
        if ($roster) {
            return [$roster->is_rest_day ? null : $roster->shift, $roster->is_rest_day, true];
        }

        $assignments = $this->schedules->assignmentsQuery([$employee->id], $date, $date)->get();
        $day = $this->schedules->resolveDay($assignments, CarbonImmutable::parse($date), null);

        if (! $day['has_schedule']) {
            return [null, false, false];
        }

        return $day['is_working_day'] && $day['shift']
            ? [Shift::find($day['shift']['id']), false, true]
            : [null, true, true];
    }

    /** Non-working holiday for the employee's jurisdiction (null country = applies everywhere). */
    protected function holiday(string $date, ?string $country, ?string $subdivision): ?Holiday
    {
        $day = CarbonImmutable::parse($date);
        $candidates = $this->holidays->getForRange($day, $day)
            ->filter(fn (Holiday $h) => $h->country_code === null || $h->country_code === $country)
            ->filter(fn (Holiday $h) => $h->subdivision_code === null || $h->subdivision_code === $subdivision);

        $holiday = $this->holidays->matchDate($candidates, $day);

        return $holiday && ! $holiday->is_working_day ? $holiday : null;
    }

    /**
     * Approved leave on the date, in days. Uses the leave date's recorded day
     * fraction; otherwise half/full day, or hours over the shift's required hours.
     */
    protected function leaveDays(Employee $employee, string $date, ?Shift $shift): string
    {
        $dates = LeaveApplicationDate::whereDate('leave_date', $date)
            ->whereHas('application', fn ($q) => $q->where('employee_id', $employee->id)->where('status', LeaveApplication::STATUS_APPROVED))
            ->get(['duration_type', 'hours', 'day_fraction']);

        $total = '0';
        foreach ($dates as $leave) {
            $days = match (true) {
                $leave->day_fraction !== null => (string) $leave->day_fraction,
                $leave->duration_type === 'half_day' => '0.5',
                $leave->duration_type === 'hours' && $shift && (float) $shift->required_hours > 0 => bcdiv((string) $leave->hours, (string) $shift->required_hours, 4),
                default => '1',
            };
            $total = bcadd($total, $days, 4);
        }

        return bccomp($total, '1', 4) > 0 ? '1.0000' : $total;
    }
}
