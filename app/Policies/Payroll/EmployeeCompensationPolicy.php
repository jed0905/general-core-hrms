<?php

namespace App\Policies\Payroll;

use App\Models\EmployeeCompensation;
use App\Models\User;
use App\Services\Payroll\PayrollScope;

/**
 * Salary records. Viewing, recording and approving are separate permissions;
 * the person who recorded a change can't approve it. Scoped by the
 * assignment's legal entity.
 */
class EmployeeCompensationPolicy
{
    public function __construct(protected PayrollScope $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->can('compensation.view');
    }

    public function create(User $user): bool
    {
        return $user->can('compensation.create');
    }

    public function update(User $user, EmployeeCompensation $compensation): bool
    {
        return $user->can('compensation.update') && $compensation->status === EmployeeCompensation::STATUS_PENDING && $this->inScope($user, $compensation);
    }

    public function approve(User $user, EmployeeCompensation $compensation): bool
    {
        return $user->can('compensation.approve')
            && $compensation->status === EmployeeCompensation::STATUS_PENDING
            && (int) $compensation->created_by !== $user->id
            && $this->inScope($user, $compensation);
    }

    public function delete(User $user, EmployeeCompensation $compensation): bool
    {
        return $this->update($user, $compensation);
    }

    private function inScope(User $user, EmployeeCompensation $compensation): bool
    {
        return $this->scope->allows($user, (int) $compensation->assignment->legal_entity_id);
    }
}
