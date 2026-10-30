<?php

namespace App\Policies\Payroll;

use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Payroll\PayrollScope;

/**
 * Payroll runs. Every ability = permission AND legal-entity scope AND run state.
 * Separation of duties: whoever calculated a run can't review, approve or
 * finalize it, and its reviewer can't approve it. PayrollRunService re-checks
 * every rule inside its transaction. Role names are never checked.
 */
class PayrollRunPolicy
{
    public function __construct(protected PayrollScope $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->can('payroll.view');
    }

    public function view(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.view') && $this->inScope($user, $run);
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.create');
    }

    /** Include or exclude employees. */
    public function manageEmployees(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.update') && $this->inScope($user, $run)
            && $run->run_type !== PayrollRun::TYPE_REVERSAL
            && in_array($run->status, [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_CALCULATED], true);
    }

    public function calculate(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.calculate') && $this->inScope($user, $run)
            && $run->run_type !== PayrollRun::TYPE_REVERSAL
            && in_array($run->status, [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_CALCULATED], true);
    }

    public function review(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.review') && $this->inScope($user, $run)
            && $run->status === PayrollRun::STATUS_CALCULATED
            && (int) $run->calculated_by !== $user->id;
    }

    public function approve(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.approve') && $this->inScope($user, $run)
            && $run->status === PayrollRun::STATUS_REVIEWED
            && ! in_array($user->id, [(int) $run->calculated_by, (int) $run->reviewed_by], true);
    }

    public function returnToDraft(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.reject') && $this->inScope($user, $run)
            && $run->run_type !== PayrollRun::TYPE_REVERSAL
            && in_array($run->status, PayrollRun::RETURNABLE, true);
    }

    public function cancel(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.reject') && $this->inScope($user, $run) && ! $run->isTerminal();
    }

    public function finalize(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.finalize') && $this->inScope($user, $run)
            && $run->status === PayrollRun::STATUS_APPROVED
            && (int) $run->calculated_by !== $user->id;
    }

    public function reverse(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.reverse') && $this->inScope($user, $run)
            && $run->isFinalized()
            && $run->run_type !== PayrollRun::TYPE_REVERSAL
            && ! $run->reversal()->exists();
    }

    public function export(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.export') && $this->inScope($user, $run);
    }

    public function disburse(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.disburse') && $this->inScope($user, $run) && $run->isFinalized();
    }

    public function delete(User $user, PayrollRun $run): bool
    {
        return false;
    }

    private function inScope(User $user, PayrollRun $run): bool
    {
        return $this->scope->allows($user, (int) $run->legal_entity_id);
    }
}
