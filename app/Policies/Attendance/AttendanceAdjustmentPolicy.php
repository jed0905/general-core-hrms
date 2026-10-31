<?php

namespace App\Policies\Attendance;

use App\Models\AttendanceAdjustment;
use App\Models\User;
use App\Services\Attendance\AttendanceScope;

/**
 * An adjustment may be decided by a user with attendance.approve_adjustment
 * who can see the employee, while it is pending, and never by its requester.
 */
class AttendanceAdjustmentPolicy
{
    public function __construct(protected AttendanceScope $scope) {}

    public function decide(User $user, AttendanceAdjustment $adjustment): bool
    {
        return $user->can('attendance.approve_adjustment')
            && $adjustment->status === AttendanceAdjustment::STATUS_PENDING
            && (int) $adjustment->requested_by !== $user->id
            && $this->scope->allows($user, (int) $adjustment->employee_id);
    }
}
