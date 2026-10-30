<?php

namespace App\Services\Payroll\Inputs;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollTimeInput;
use App\Services\Payroll\Contracts\TimeInputProvider;

/**
 * Time quantities from approved payroll_time_inputs (entered or imported by
 * payroll staff). There is no DTR engine in the HRMS yet; when one exists it
 * replaces this binding with a provider that reads its approved results.
 */
class ApprovedTimeInputProvider implements TimeInputProvider
{
    public function quantities(Employee $employee, PayrollPeriod $period): array
    {
        return PayrollTimeInput::query()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->where('status', PayrollTimeInput::STATUS_APPROVED)
            ->pluck('quantity', 'category')
            ->map(fn ($quantity) => (string) $quantity)
            ->all();
    }
}
