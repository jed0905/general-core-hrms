<?php

namespace App\Services\Reports\Attendance;

use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;

class AttendanceSummaryReport extends Report
{
    use RawAttendance;

    public static function key(): string
    {
        return 'attendance-summary';
    }

    public static function category(): string
    {
        return 'attendance';
    }

    public function title(): string
    {
        return 'Raw Attendance Summary';
    }

    public function description(): string
    {
        return 'Per employee per day: number of punches, first punch and last punch.';
    }

    public function filters(): array
    {
        return $this->attendanceLogs()->filters(['date_from', 'date_to', 'search', 'department_id', 'include_sub_departments', 'location_id', 'unregistered_only']);
    }

    public function rules(): array
    {
        return $this->attendanceLogs()->rules();
    }

    public function notes(): array
    {
        return ['Raw Attendance Summary: counts and first/last punch only. Late, undertime, absences, overtime and work hours are not calculated (that belongs to the future DTR module).'];
    }

    public function columns(array $filters = []): array
    {
        return [
            'date' => 'Date',
            'biometric_id' => 'Biometric ID',
            'employee_number' => 'Employee No.',
            'employee' => 'Employee',
            'department' => 'Department',
            'punches' => 'Punches',
            'first_punch' => 'First Punch',
            'last_punch' => 'Last Punch',
        ];
    }

    public function sortable(): array
    {
        return ['date' => 'al.auth_date', 'employee' => 'e.emp_last_name', 'punches' => 'count(*)'];
    }

    protected function tiebreaker(): string|array
    {
        return ['al.auth_date', 'al.employee_id'];
    }

    public function query(array $filters): Builder
    {
        // Every selected non-aggregate is grouped (strict SQL mode).
        return $this->punches($filters)
            ->selectRaw('al.auth_date, al.employee_id as biometric_id, b.id as registration_id, e.employee_number, e.emp_last_name, e.emp_first_name, e.department_id, count(*) as punches, min(al.auth_time) as first_punch, max(al.auth_time) as last_punch')
            ->groupBy('al.auth_date', 'al.employee_id', 'b.id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id')
            ->orderByDesc('al.auth_date')
            ->orderBy('e.emp_last_name')
            ->orderBy('al.employee_id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'date' => substr((string) $row->auth_date, 0, 10),
            'biometric_id' => $row->biometric_id,
            'employee_number' => $row->employee_number,
            'employee' => $row->registration_id ? self::personName($row->emp_last_name, $row->emp_first_name) : 'Unregistered ID',
            'department' => $this->tree()->path($row->department_id),
            'punches' => (int) $row->punches,
            'first_punch' => $row->first_punch,
            'last_punch' => $row->last_punch,
        ];
    }
}
