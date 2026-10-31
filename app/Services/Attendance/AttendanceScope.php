<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Whose attendance a user may see. attendance.scope.all: everyone; otherwise
 * the user's direct reports (employees.supervisor_id) and the user themselves.
 * Applied in policies, list queries and exports. Never decided by role name.
 */
class AttendanceScope
{
    /** @return array<int, int>|null employee ids, or null for everyone */
    public function employeeIds(User $user): ?array
    {
        if ($user->can('attendance.scope.all')) {
            return null;
        }

        if ($user->employee_id === null) {
            return [];
        }

        return Employee::where('supervisor_id', $user->employee_id)->pluck('id')->push((int) $user->employee_id)->map(fn ($id) => (int) $id)->all();
    }

    public function allows(User $user, int $employeeId): bool
    {
        $ids = $this->employeeIds($user);

        return $ids === null || in_array($employeeId, $ids, true);
    }

    public function apply(Builder $query, User $user, string $column = 'employee_id'): Builder
    {
        $ids = $this->employeeIds($user);

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }
}
