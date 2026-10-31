<?php

namespace App\Services\Attendance;

use App\Models\AttendancePolicy;
use App\Models\EmployeeShiftAssignment;
use App\Models\User;
use App\Services\Attendance\Engine\AttendanceRecalculator;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Attendance policies (company rules per legal entity) and the date-specific
 * roster that overrides weekly schedules. Changing a roster recalculates the
 * affected days.
 */
class AttendanceSetupService
{
    public function __construct(
        protected AttendanceRecalculator $recalculator,
        protected AttendanceAuditLogger $audit,
    ) {}

    public function savePolicy(array $data, User $user, ?AttendancePolicy $policy = null): AttendancePolicy
    {
        $policy ??= new AttendancePolicy;
        $policy->fill($data);
        $changes = $this->audit->pendingChanges($policy);
        $policy->save();
        $this->audit->record($policy, 'saved', $user, $changes);

        return $policy;
    }

    /**
     * Assign a shift (or rest day) to employees for a date range, replacing
     * existing roster entries; null shift and not rest day clears the override.
     *
     * @param  array<int, int>  $employeeIds
     */
    public function assignRoster(array $employeeIds, string $from, string $to, ?int $shiftId, bool $restDay, ?string $remarks, User $user): int
    {
        $count = DB::transaction(function () use ($employeeIds, $from, $to, $shiftId, $restDay, $remarks, $user) {
            $count = 0;
            foreach ($employeeIds as $employeeId) {
                foreach (CarbonPeriod::create($from, $to) as $date) {
                    $existing = EmployeeShiftAssignment::where('employee_id', $employeeId)->whereDate('work_date', $date->toDateString())->first();
                    if ($shiftId === null && ! $restDay) {
                        $existing?->delete();
                    } else {
                        ($existing ?? new EmployeeShiftAssignment(['employee_id' => $employeeId, 'work_date' => $date->toDateString()]))
                            ->fill(['shift_id' => $restDay ? null : $shiftId, 'is_rest_day' => $restDay, 'remarks' => $remarks, 'created_by' => $user->id])
                            ->save();
                    }
                    $count++;
                }
            }

            return $count;
        });

        $this->recalculator->recalculateRange($employeeIds, $from, $to);

        return $count;
    }
}
