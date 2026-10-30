<?php

namespace App\Services\Payroll\Contracts;

use App\Models\Employee;
use App\Models\PayrollPeriod;

/**
 * Supplies approved time quantities (days/hours worked, absences, late,
 * undertime, premium hours by category) for one employee and period. The engine
 * never reads attendance punches itself.
 */
interface TimeInputProvider
{
    /** @return array<string, string> category => quantity (decimal string) */
    public function quantities(Employee $employee, PayrollPeriod $period): array;
}
