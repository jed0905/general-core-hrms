<?php

namespace App\Policies\Payroll;

use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\User;
use App\Services\Payroll\PayrollScope;

/**
 * Payslips (payroll_run_employees). Payroll staff with payslip.view see the
 * payslips of entities in their scope; an employee with payslip.view_own sees
 * only their own, and only once the run is finalized.
 */
class PayslipPolicy
{
    public function __construct(protected PayrollScope $scope) {}

    public function viewOwn(User $user): bool
    {
        return $user->can('payslip.view_own') && $user->employee_id !== null;
    }

    public function view(User $user, PayrollRunEmployee $payslip): bool
    {
        $run = $payslip->run;

        if ($user->can('payslip.view') && $this->scope->allows($user, (int) $run->legal_entity_id)) {
            return true;
        }

        return $user->can('payslip.view_own')
            && $user->employee_id !== null
            && (int) $payslip->employee_id === (int) $user->employee_id
            && $payslip->status === PayrollRunEmployee::STATUS_INCLUDED
            && $run->status === PayrollRun::STATUS_FINALIZED;
    }
}
