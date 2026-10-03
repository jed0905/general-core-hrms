<?php

namespace App\Services\Reports\Attendance;

use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;

class AttendanceLogReport extends Report
{
    use RawAttendance;

    public static function key(): string
    {
        return 'attendance-log';
    }

    public static function category(): string
    {
        return 'attendance';
    }

    public function title(): string
    {
        return 'Attendance Log';
    }

    public function description(): string
    {
        return 'Raw time-in/time-out punches from the attendance devices.';
    }

    public function filters(): array
    {
        return $this->attendanceLogs()->filters(['date_from', 'date_to', 'search', 'department_id', 'include_sub_departments', 'location_id', 'log_direction', 'device_name', 'unregistered_only']);
    }

    public function rules(): array
    {
        return $this->attendanceLogs()->rules();
    }

    public function notes(): array
    {
        return ['Raw attendance logs as recorded by the devices. This is not a computed DTR: no late, undertime, absence or hours are calculated.'];
    }

    public function columns(array $filters = []): array
    {
        return [
            'date' => 'Date',
            'time' => 'Time',
            'direction' => 'Direction',
            'device' => 'Device',
            'device_serial' => 'Device Serial',
            'biometric_id' => 'Biometric ID',
            'employee_number' => 'Employee No.',
            'employee' => 'Employee',
            'department' => 'Department',
        ];
    }

    public function sortable(): array
    {
        return ['date' => 'al.auth_date_time', 'employee' => 'e.emp_last_name', 'device' => 'al.device_name'];
    }

    protected function tiebreaker(): string|array
    {
        return 'al.id';
    }

    public function query(array $filters): Builder
    {
        return $this->attendanceLogs()->query($filters);
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return $this->attendanceLogs()->mapRow($row);
    }
}
