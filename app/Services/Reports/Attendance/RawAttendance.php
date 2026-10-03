<?php

namespace App\Services\Reports\Attendance;

use App\Services\AttendanceLogService;
use App\Services\Reports\Concerns\FiltersEmployees;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * The attendance reports delegate to AttendanceLogService, the same query
 * and filters used by Time → Time Logs.
 */
trait RawAttendance
{
    use FiltersEmployees;

    public static function maxDays(): int
    {
        return AttendanceLogService::MAX_DAYS;
    }

    protected function attendanceLogs(): AttendanceLogService
    {
        return app(AttendanceLogService::class);
    }

    protected function punches(array $filters): Builder
    {
        return $this->attendanceLogs()->filtered($filters);
    }
}
