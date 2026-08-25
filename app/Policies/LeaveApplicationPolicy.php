<?php

namespace App\Policies;

use App\Models\LeaveApplication;
use App\Models\User;

class LeaveApplicationPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LeaveApplication $leave): bool
    {
        return LeaveApplication::visibleTo($user)
            ->where('id', $leave->id)
            ->exists();
    }

    /**
     * Determine whether the user can recommend leave.
     */
    public function recommend(User $user, LeaveApplication $leave): bool
    {
        if ($leave->currentStatus?->status !== 'pending') {
            return false;
        }

        if (!$user->employee) {
            return false;
        }

        return $leave->employee->immediate_supervisor_id === $user->employee->id;
    }

    /**
     * Determine whether the user can certify the leave.
     */
    public function certify(User $user, LeaveApplication $leave): bool
    {
        $allowedStatuses = ['for approval', 'for disapproval'];

        if (!in_array($leave->currentStatus?->status, $allowedStatuses)) {
            return false;
        }

        return $user->hasAnyRole([
            'campus_hr',
            'campus_hr_staff',
            'hr_director'
        ]);
    }

    /**
     * Determine whether the user can approve the leave.
     */
    public function approve(User $user, LeaveApplication $leave): bool
    {
        // Must be certified first
        if ($leave->currentStatus?->status !== 'certified') {
            return false;
        }

        $employee = $user->employee;

        if (!$employee) {
            return false;
        }

        // Get designations
        $designations = $employee->employeeDesignations
            ->pluck('designation.name')
            ->map(fn($name) => strtolower(trim($name)))
            ->toArray();

        // Compute total credits
        $totalCredits = $leave->leaveDates->sum('credits');

        $isPresident = in_array('university president', $designations);

        $isHead = count(array_intersect($designations, [
            'chancellor',
            'executive director',
        ])) > 0;

        // ≥ 30 days → ONLY UNIVERSITY PRESIDENT
        if ($totalCredits >= 30) {
            return $isPresident;
        }

        // < 30 days → HEADS OR UNIVERSITY PRESIDENT
        if ($totalCredits < 30) {
            return $isPresident || $isHead;
        }

        return false;
    }

    public function cancel(User $user, LeaveApplication $leave): bool
    {
        $employee = $user->employee;

        $status = strtolower($leave->currentStatus?->status ?? '');

        // ❌ FINAL STATES: cannot cancel anymore
        if (in_array($status, ['cancelled', 'disapproved'])) {
            return false;
        }

        // ✅ Applicant can cancel their own leave
        if ($employee && $leave->employee_id === $employee->id) {
            return true;
        }

        // ✅ HR can cancel any non-final leave
        if (
            $user->hasAnyRole([
                'superadmin',
                'campus_hr',
                'campus_hr_staff',
                'hr_director'
            ])
        ) {
            return true;
        }

        return false;
    }

}
