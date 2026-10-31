<?php

namespace App\Listeners;

use App\Events\PayrollRunFinalized;
use App\Services\Attendance\AttendancePeriodService;

/** Attendance summaries used by a finalized payroll run never change afterwards. */
class LockConsumedAttendanceSummaries
{
    public function __construct(protected AttendancePeriodService $periods) {}

    public function handle(PayrollRunFinalized $event): void
    {
        $this->periods->markConsumed($event->run);
    }
}
