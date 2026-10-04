<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * Record-level access to an employee and the data that belongs to them.
 *
 * Each ability is: the organization-wide permission, or the matching
 * `*_own` permission when the employee is the user's own record
 * (users.employee_id). Role names are never checked.
 */
class EmployeePolicy
{
    public function view(User $user, Employee $employee): bool
    {
        return $user->can('employee.view')
            || ($this->isSelf($user, $employee) && $user->can('employee.view_own'));
    }

    public function create(User $user): bool
    {
        return $user->can('employee.create');
    }

    /**
     * Full HR edit of the employee record (self-service uses updateContactDetails).
     */
    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employee.update');
    }

    /**
     * Self-service updates are limited to contact details by the form request.
     */
    public function updateContactDetails(User $user, Employee $employee): bool
    {
        return $user->can('employee.update')
            || ($this->isSelf($user, $employee) && $user->can('employee.update_own'));
    }

    public function viewWorkSchedule(User $user, Employee $employee): bool
    {
        return $user->can('employee_work_schedule.view')
            || ($this->isSelf($user, $employee) && $user->can('work_schedule.view_own'));
    }

    public function viewAttendance(User $user, Employee $employee): bool
    {
        return $user->can('attendance.view')
            || ($this->isSelf($user, $employee) && $user->can('attendance.view_own'));
    }

    public function exportAttendance(User $user, Employee $employee): bool
    {
        return $user->can('attendance.export')
            || ($this->isSelf($user, $employee) && $user->can('attendance.export_own'));
    }

    public function viewLeaveBalance(User $user, Employee $employee): bool
    {
        return $user->can('leave.balance.view')
            || ($this->isSelf($user, $employee) && $user->can('leave.view_balance_own'));
    }

    private function isSelf(User $user, Employee $employee): bool
    {
        return $user->employee_id !== null && (int) $user->employee_id === (int) $employee->id;
    }
}
