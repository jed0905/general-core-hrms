<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationDate;
use App\Models\User;
use App\Services\Leave\LeaveApplicationService;

/**
 * The employee self-service dashboard.
 *
 * Everything is about the user's own employee record (users.employee_id);
 * nothing from the request decides whose data is shown. Each section is
 * only built when the user holds the matching self-service permission and
 * the EmployeePolicy agrees, otherwise it is null.
 */
class EmployeeDashboardService
{
    public function __construct(
        protected EmployeeProfileService $profiles,
        protected EmployeeWorkScheduleService $schedules,
        protected EmployeeAttendanceService $attendance,
        protected LeaveApplicationService $leave,
    ) {}

    public function build(User $user): array
    {
        $employee = $user->employee_id ? Employee::find($user->employee_id) : null;

        if (! $employee) {
            return [
                'hasEmployeeRecord' => false,
                'employee' => null,
                'today' => null,
                'nextWorkingDay' => null,
                'attendance' => null,
                'leaveBalances' => null,
                'recentLeave' => null,
            ];
        }

        $today = now();
        $canSchedule = $user->can('viewWorkSchedule', $employee);
        $canAttendance = $user->can('viewAttendance', $employee);
        $todaySchedule = ($canSchedule || $canAttendance) ? $this->schedules->getDaySchedule($employee, $today) : null;

        return [
            'hasEmployeeRecord' => true,
            'employee' => $user->can('view', $employee) ? $this->summary($employee) : null,
            'today' => $canSchedule ? $todaySchedule : null,
            'nextWorkingDay' => $canSchedule ? $this->schedules->getNextWorkingDay($employee, $today) : null,
            'attendance' => $canAttendance ? $this->attendanceToday($user, $employee, $todaySchedule) : null,
            'leaveBalances' => $user->can('viewLeaveBalance', $employee) ? $this->leave->getBalanceSummary($employee->id) : null,
            'recentLeave' => $user->can('leave.view_own') ? $this->leave->getRecentApplications($employee->id) : null,
        ];
    }

    private function summary(Employee $employee): array
    {
        $profile = $this->profiles->getProfile($employee);

        return [
            'name' => trim($employee->emp_first_name.' '.$employee->emp_last_name),
            'employee_number' => $profile['employee_number'],
            'job_title' => $profile['job_title'],
            'department' => $profile['department'],
            'location' => $profile['location'],
        ];
    }

    /**
     * Today's punches plus a plain status. This is not a DTR computation:
     * lateness/undertime belong to the future attendance service.
     */
    private function attendanceToday(User $user, Employee $employee, ?array $todaySchedule): array
    {
        $todayDate = now()->toDateString();
        $logs = $this->attendance->getLogsForDate($employee, $todayDate);

        $onLeave = $user->can('leave.view_own') && LeaveApplicationDate::query()
            ->where('leave_date', $todayDate)
            ->whereHas('application', fn ($q) => $q
                ->where('employee_id', $employee->id)
                ->where('status', LeaveApplication::STATUS_APPROVED))
            ->exists();

        $status = match (true) {
            $logs->isNotEmpty() => $logs->count() > 1 ? 'timed_in_and_out' : 'timed_in',
            $onLeave => 'on_leave',
            $todaySchedule && $todaySchedule['holiday'] && ! $todaySchedule['holiday']['is_working_day'] => 'holiday',
            $todaySchedule && $todaySchedule['has_schedule'] && ! $todaySchedule['is_working_day'] => 'rest_day',
            default => 'no_record',
        };

        return [
            'status' => $status,
            'first_in' => $logs->first()?->auth_time,
            'last_out' => $logs->count() > 1 ? $logs->last()->auth_time : null,
            'has_biometric_id' => $this->attendance->hasBiometricId($employee),
            'recent' => $this->attendance->getRecentLogs($employee),
        ];
    }
}
