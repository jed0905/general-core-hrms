<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\User;

/**
 * Record-level rules for employee movements. Capabilities come from the
 * employee_movement.* permissions; role names are never checked.
 */
class EmployeeMovementPolicy
{
    /**
     * HR with employee_movement.view sees any movement; an employee with
     * view_own sees their own movements once they are in effect.
     */
    public function view(User $user, EmployeeMovement $movement): bool
    {
        if ($user->can('employee_movement.view')) {
            return true;
        }

        return $user->can('employee_movement.view_own')
            && $this->isSelf($user, $movement->employee_id)
            && $movement->status === EmployeeMovement::STATUS_EFFECTIVE;
    }

    /**
     * Nobody records employment changes for their own employee record.
     */
    public function create(User $user, Employee $employee): bool
    {
        return $user->can('employee_movement.create')
            && ! $this->isSelf($user, $employee->id);
    }

    /**
     * Descriptive fields only (the service enforces which fields).
     */
    public function update(User $user, EmployeeMovement $movement): bool
    {
        return $user->can('employee_movement.update')
            && $movement->status !== EmployeeMovement::STATUS_CANCELLED
            && ! $this->isSelf($user, $movement->employee_id);
    }

    /**
     * Withdraw a scheduled movement or reverse an effective one.
     */
    public function cancel(User $user, EmployeeMovement $movement): bool
    {
        return $user->can('employee_movement.cancel')
            && in_array($movement->status, [EmployeeMovement::STATUS_SCHEDULED, EmployeeMovement::STATUS_EFFECTIVE], true)
            && ! $this->isSelf($user, $movement->employee_id);
    }

    private function isSelf(User $user, int|string|null $employeeId): bool
    {
        return $user->employee_id !== null && (int) $user->employee_id === (int) $employeeId;
    }
}
