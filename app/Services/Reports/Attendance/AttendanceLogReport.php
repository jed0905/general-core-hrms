<?php

namespace App\Services\Reports\Attendance;

use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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
        return [
            ...$this->rangeFilters(),
            ...$this->employeeFilters(['search', 'department_id', 'include_sub_departments']),
            ['key' => 'direction', 'label' => 'Direction', 'type' => 'select', 'options' => DB::table('employee_attendance_logs')->distinct()->orderBy('direction')->pluck('direction')
                ->map(fn ($d) => ['value' => $d, 'title' => $d])->all()],
            ['key' => 'device_name', 'label' => 'Device', 'type' => 'select', 'options' => DB::table('employee_attendance_logs')->distinct()->orderBy('device_name')->pluck('device_name')
                ->map(fn ($d) => ['value' => $d, 'title' => $d])->all()],
            ['key' => 'unregistered_only', 'label' => 'Unregistered IDs only', 'type' => 'boolean', 'default' => false],
        ];
    }

    public function rules(): array
    {
        return array_merge($this->employeeFilterRules(), $this->rangeRules(), [
            'direction' => ['nullable', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'unregistered_only' => ['nullable', 'boolean'],
        ]);
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
        return $this->punches($filters)
            ->when(! empty($filters['direction']), fn ($q) => $q->where('al.direction', $filters['direction']))
            ->when(! empty($filters['device_name']), fn ($q) => $q->where('al.device_name', $filters['device_name']))
            ->select(['al.id', 'al.auth_date', 'al.auth_time', 'al.direction', 'al.device_name', 'al.device_sn', 'al.employee_id as biometric_id', 'b.id as registration_id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id'])
            ->orderByDesc('al.auth_date_time')
            ->orderByDesc('al.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'date' => substr((string) $row->auth_date, 0, 10),
            'time' => $row->auth_time,
            'direction' => $row->direction,
            'device' => $row->device_name,
            'device_serial' => $row->device_sn,
            'biometric_id' => $row->biometric_id,
            'employee_number' => $row->employee_number,
            'employee' => $row->registration_id ? self::personName($row->emp_last_name, $row->emp_first_name) : 'Unregistered ID',
            'department' => $this->tree()->path($row->department_id),
        ];
    }
}
