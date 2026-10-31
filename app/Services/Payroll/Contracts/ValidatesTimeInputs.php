<?php

namespace App\Services\Payroll\Contracts;

use App\Models\Employee;
use App\Models\PayrollPeriod;

/**
 * Optional TimeInputProvider capability: problems that must stop the
 * employee's calculation (e.g. attendance not yet approved for the period).
 */
interface ValidatesTimeInputs
{
    /** @return array<int, string> error messages; empty when the inputs are usable */
    public function problems(Employee $employee, PayrollPeriod $period): array;
}
