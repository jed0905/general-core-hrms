<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAttendanceLog;
use App\Models\EmployeeBiometricId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Raw attendance punches for one employee.
 *
 * employee_attendance_logs.employee_id holds the device's biometric ID
 * (a string), not employees.id; employee_biometric_ids maps the two.
 * Callers pass an already-authorized employee.
 */
class EmployeeAttendanceService
{
    public function getPaginatedLogs(Employee $employee, array $filters, int $perPage = 31): LengthAwarePaginator
    {
        return $this->logsQuery($employee, $filters)
            ->orderByDesc('auth_date_time')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getLogsForDate(Employee $employee, string $date): Collection
    {
        return $this->logsQuery($employee, ['from' => $date, 'to' => $date])
            ->orderBy('auth_date_time')
            ->get(['id', 'auth_date', 'auth_time', 'direction', 'device_name']);
    }

    public function getRecentLogs(Employee $employee, int $limit = 5): Collection
    {
        return EmployeeAttendanceLog::query()
            ->whereIn('employee_id', EmployeeBiometricId::where('employee_id', $employee->id)->pluck('biometric_id'))
            ->orderByDesc('auth_date_time')
            ->limit($limit)
            ->get(['id', 'auth_date', 'auth_time', 'direction', 'device_name']);
    }

    public function exportLogs(Employee $employee, array $filters): StreamedResponse
    {
        [$from, $to] = $this->range($filters);
        $filename = "attendance_{$employee->employee_number}_{$from}_{$to}.csv";

        return response()->streamDownload(function () use ($employee, $filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Time', 'Direction', 'Device']);

            $this->logsQuery($employee, $filters)
                ->orderBy('auth_date_time')
                ->select(['id', 'auth_date', 'auth_time', 'direction', 'device_name'])
                ->chunkById(500, function ($logs) use ($out) {
                    foreach ($logs as $log) {
                        fputcsv($out, [$log->auth_date, $log->auth_time, $log->direction, $log->device_name]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: string, 1: string} from/to dates, defaulting to the current month
     */
    public function range(array $filters): array
    {
        return [
            $filters['from'] ?? now()->startOfMonth()->toDateString(),
            $filters['to'] ?? now()->endOfMonth()->toDateString(),
        ];
    }

    public function hasBiometricId(Employee $employee): bool
    {
        return EmployeeBiometricId::where('employee_id', $employee->id)->exists();
    }

    protected function logsQuery(Employee $employee, array $filters): Builder
    {
        [$from, $to] = $this->range($filters);

        $biometricIds = EmployeeBiometricId::where('employee_id', $employee->id)->pluck('biometric_id');

        return EmployeeAttendanceLog::query()
            ->whereIn('employee_id', $biometricIds)
            ->whereBetween('auth_date', [$from, $to]);
    }
}
