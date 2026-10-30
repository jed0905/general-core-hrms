<?php

namespace App\Services\Payroll\Inputs;

use App\Models\Employee;
use App\Services\EmployeeWorkScheduleService;
use App\Services\HolidayService;
use Carbon\CarbonPeriod;

/**
 * Scheduled working days of an employee in a date range, using the Time
 * module's single day rule (EmployeeWorkScheduleService::resolveDay: schedule
 * assignment, weekday shift, holiday). Used for "working_days" proration only.
 * Limitation: the holiday list has no jurisdiction yet (see BLOCKERS B2).
 */
class WorkingDayCounter
{
    public function __construct(
        protected EmployeeWorkScheduleService $schedules,
        protected HolidayService $holidays,
    ) {}

    public function count(Employee $employee, string $from, string $to): int
    {
        $assignments = $this->schedules->assignmentsQuery([$employee->id], $from, $to)->get();
        $count = 0;

        foreach (CarbonPeriod::create($from, $to) as $date) {
            if ($this->schedules->resolveDay($assignments, $date, $this->holidays->findForDate($date))['is_working_day']) {
                $count++;
            }
        }

        return $count;
    }
}
