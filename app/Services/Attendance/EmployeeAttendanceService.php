<?php

namespace App\Services\Attendance;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceDay;
use App\Models\AttendanceEvent;
use App\Models\BiometricEnrollment;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One employee's attendance as calculated by the attendance engine
 * (attendance_days) plus the raw events behind it (attendance_events).
 * Shared by the HR employee history, employee self-service and the employee
 * dashboard. Callers pass an already-authorized employee.
 */
class EmployeeAttendanceService
{
    public const EVENT_COLUMNS = ['id', 'attendance_device_id', 'source', 'occurred_at', 'local_date', 'event_type', 'verify_mode', 'device_timezone'];

    /**
     * @return array{0: string, 1: string} from/to dates, defaulting to the current month up to today
     */
    public function range(array $filters): array
    {
        return [
            $filters['date_from'] ?? now()->startOfMonth()->toDateString(),
            $filters['date_to'] ?? now()->toDateString(),
        ];
    }

    /** @return Collection<int, AttendanceDay> */
    public function days(Employee $employee, string $from, string $to): Collection
    {
        return AttendanceDay::with('shift:id,code,name')
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->get();
    }

    /** @return Collection<int, AttendanceEvent> accepted events only, oldest first */
    public function events(Employee $employee, string $from, string $to, int $limit = 2000): Collection
    {
        return AttendanceEvent::with('device:id,name')
            ->where('employee_id', $employee->id)
            ->where('status', AttendanceEvent::STATUS_ACCEPTED)
            ->whereBetween('local_date', [$from, $to])
            ->orderBy('occurred_at')
            ->limit($limit)
            ->get(self::EVENT_COLUMNS);
    }

    /** @return Collection<int, AttendanceAdjustment> */
    public function adjustments(Employee $employee, string $from, string $to): Collection
    {
        return AttendanceAdjustment::with(['requester:id,username', 'reviewer:id,username'])
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->orderByDesc('id')
            ->get();
    }

    public function day(Employee $employee, string $date): ?AttendanceDay
    {
        return AttendanceDay::with('shift:id,code,name')->where('employee_id', $employee->id)->whereDate('work_date', $date)->first();
    }

    /** @return Collection<int, AttendanceEvent> newest first */
    public function recentEvents(Employee $employee, int $limit = 5): Collection
    {
        return AttendanceEvent::with('device:id,name')
            ->where('employee_id', $employee->id)
            ->where('status', AttendanceEvent::STATUS_ACCEPTED)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get(self::EVENT_COLUMNS);
    }

    public function hasActiveEnrollment(Employee $employee): bool
    {
        return BiometricEnrollment::where('employee_id', $employee->id)->where('status', BiometricEnrollment::STATUS_ACTIVE)->exists();
    }

    /** CSV of calculated days in the range. Times are shown in each day's own timezone. */
    public function export(Employee $employee, string $from, string $to): StreamedResponse
    {
        $filename = "attendance_{$employee->employee_number}_{$from}_{$to}.csv";

        return response()->streamDownload(function () use ($employee, $from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Day type', 'Shift', 'First in', 'Last out', 'Worked (min)', 'Late (min)', 'Undertime (min)', 'Approved OT (min)', 'Leave (days)', 'Status']);

            foreach ($this->days($employee, $from, $to) as $day) {
                fputcsv($out, [
                    $day->work_date->toDateString(),
                    $day->day_type,
                    $day->shift?->code,
                    $day->first_in?->copy()->setTimezone($day->timezone)->format('H:i'),
                    $day->last_out?->copy()->setTimezone($day->timezone)->format('H:i'),
                    $day->worked_minutes,
                    $day->late_minutes,
                    $day->undertime_minutes,
                    $day->approved_overtime_minutes,
                    $day->leave_days,
                    $day->status,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
