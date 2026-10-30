<?php

namespace App\Services\Payroll\Contracts;

use App\Models\Employee;

/**
 * Supplies approved leave within a date range. Pending, rejected, returned and
 * cancelled leave is never included.
 */
interface LeaveInputProvider
{
    /**
     * @return array{paid_days: string, unpaid_days: string, dates: array<int, array{date: string, paid: bool, days: string, application_id: int}>}
     */
    public function summary(Employee $employee, string $from, string $to, string $hoursPerDay): array;
}
