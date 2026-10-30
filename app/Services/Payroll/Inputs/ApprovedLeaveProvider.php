<?php

namespace App\Services\Payroll\Inputs;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationDate;
use App\Services\Payroll\Contracts\LeaveInputProvider;
use App\Services\Payroll\Support\Decimal;

/**
 * Leave for payroll, read from the Leave module. Only approved applications
 * count; is_paid on each date (set by the leave policy) decides paid vs unpaid.
 * Days per date: the date's day_fraction when recorded, otherwise 1 for a full
 * day, 0.5 for a half day, and hours / hours-per-day for an hourly date.
 */
class ApprovedLeaveProvider implements LeaveInputProvider
{
    public function summary(Employee $employee, string $from, string $to, string $hoursPerDay): array
    {
        $dates = LeaveApplicationDate::query()
            ->whereBetween('leave_date', [$from, $to])
            ->whereHas('application', fn ($q) => $q
                ->where('employee_id', $employee->id)
                ->where('status', LeaveApplication::STATUS_APPROVED))
            ->orderBy('leave_date')
            ->get(['id', 'leave_application_id', 'leave_date', 'duration_type', 'hours', 'day_fraction', 'is_paid']);

        $paid = '0';
        $unpaid = '0';
        $details = [];

        foreach ($dates as $date) {
            $days = $this->days($date, $hoursPerDay);
            $date->is_paid ? $paid = Decimal::add($paid, $days) : $unpaid = Decimal::add($unpaid, $days);
            $details[] = [
                'date' => substr((string) $date->leave_date, 0, 10),
                'paid' => (bool) $date->is_paid,
                'days' => Decimal::format($days, 4),
                'application_id' => $date->leave_application_id,
            ];
        }

        return ['paid_days' => Decimal::format($paid, 4), 'unpaid_days' => Decimal::format($unpaid, 4), 'dates' => $details];
    }

    private function days(LeaveApplicationDate $date, string $hoursPerDay): string
    {
        if ($date->day_fraction !== null) {
            return (string) $date->day_fraction;
        }

        return match ($date->duration_type) {
            'half_day' => '0.5',
            'hours' => Decimal::div((string) ($date->hours ?? '0'), $hoursPerDay),
            default => '1',
        };
    }
}
