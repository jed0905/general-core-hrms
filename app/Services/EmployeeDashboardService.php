<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationDate;
use App\Models\User;
use App\Services\Attendance\EmployeeAttendanceService;
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
     * Today's punches and status from the attendance module (attendance_events
     * and the engine's attendance_days row, when it has been calculated).
     */
    private function attendanceToday(User $user, Employee $employee, ?array $todaySchedule): array
    {
        $todayDate = now()->toDateString();
        $events = $this->attendance->events($employee, $todayDate, $todayDate);
        $day = $this->attendance->day($employee, $todayDate);

        $onLeave = $user->can('leave.view_own') && LeaveApplicationDate::query()
            ->where('leave_date', $todayDate)
            ->whereHas('application', fn ($q) => $q
                ->where('employee_id', $employee->id)
                ->where('status', LeaveApplication::STATUS_APPROVED))
            ->exists();

        $status = match (true) {
            $events->isNotEmpty() => $events->count() > 1 ? 'timed_in_and_out' : 'timed_in',
            $onLeave => 'on_leave',
            $todaySchedule && $todaySchedule['holiday'] && ! $todaySchedule['holiday']['is_working_day'] => 'holiday',
            $todaySchedule && $todaySchedule['has_schedule'] && ! $todaySchedule['is_working_day'] => 'rest_day',
            default => 'no_record',
        };

        return [
            'status' => $status,
            'day_status' => $day?->status,
            'late_minutes' => $day?->late_minutes,
            'first_in' => $events->isNotEmpty() ? $this->localTime($events->first()) : null,
            'last_out' => $events->count() > 1 ? $this->localTime($events->last()) : null,
            'has_biometric_id' => $this->attendance->hasActiveEnrollment($employee),
            'recent' => $this->attendance->recentEvents($employee)->map(fn (AttendanceEvent $event) => [
                'id' => $event->id,
                'date' => $event->local_date?->toDateString(),
                'time' => $this->localTime($event),
                'type' => $event->event_type,
                'device' => $event->device?->name,
            ])->all(),
        ];
    }

    /** The punch time on the device's own clock, as the employee saw it. */
    private function localTime(AttendanceEvent $event): string
    {
        return $event->occurred_at->copy()->setTimezone($event->device_timezone ?: config('app.timezone'))->format('H:i:s');
    }
}
