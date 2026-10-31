<?php

namespace App\Services\Reports\Attendance;

use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Attendance totals per employee over a date range. */
class AttendanceTotalsReport extends Report
{
    use FiltersEmployees;
    use ScopesAttendance;

    public static function key(): string
    {
        return 'attendance-totals';
    }

    public static function category(): string
    {
        return 'attendance';
    }

    public function title(): string
    {
        return 'Attendance Totals';
    }

    public function description(): string
    {
        return 'Days present, absent and on leave, worked time, lateness, undertime and overtime per employee.';
    }

    public function filters(): array
    {
        return [
            ['key' => 'date_from', 'label' => 'From', 'type' => 'date', 'required' => true, 'default' => now()->startOfMonth()->toDateString()],
            ['key' => 'date_to', 'label' => 'To', 'type' => 'date', 'required' => true, 'default' => now()->toDateString()],
            ...$this->employeeFilters(['search', 'department_id', 'include_sub_departments', 'location_id']),
        ];
    }

    public function rules(): array
    {
        return [...$this->employeeFilterRules(), 'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from']];
    }

    public function columns(array $filters = []): array
    {
        return [
            'employee_number' => 'Employee No.', 'employee' => 'Employee', 'department' => 'Department', 'days_worked' => 'Days Worked',
            'absent' => 'Absences', 'incomplete' => 'Missing Punches', 'late_days' => 'Days Late', 'leave' => 'Leave Days',
            'worked' => 'Worked', 'late' => 'Late', 'undertime' => 'Undertime', 'overtime' => 'Overtime', 'night' => 'Night',
        ];
    }

    protected function tiebreaker(): string|array
    {
        return 'e.id';
    }

    public function query(array $filters): Builder
    {
        return $this->scopeEmployees($this->applyEmployeeFilters(
            DB::table('attendance_days as d')->join('employees as e', 'e.id', '=', 'd.employee_id')
                ->whereBetween('d.work_date', [$filters['date_from'], $filters['date_to']]),
            $filters
        ))
            ->selectRaw("e.id, e.employee_number, e.emp_last_name, e.emp_first_name, e.department_id,
                sum(case when d.worked_minutes > 0 then 1 else 0 end) as days_worked, sum(d.absence_days) as absent,
                sum(case when d.status = 'incomplete' then 1 else 0 end) as incomplete, sum(case when d.late_minutes > 0 then 1 else 0 end) as late_days,
                sum(d.leave_days) as leave_days, sum(d.worked_minutes) as worked, sum(d.late_minutes) as late, sum(d.undertime_minutes) as undertime,
                sum(d.approved_overtime_minutes) as overtime, sum(d.night_minutes) as night")
            ->groupBy('e.id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id')
            ->orderBy('e.emp_last_name')
            ->orderBy('e.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        $days = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

        return [
            'employee_number' => $row->employee_number, 'employee' => self::personName($row->emp_last_name, $row->emp_first_name),
            'department' => $this->tree()->path($row->department_id), 'days_worked' => (int) $row->days_worked, 'absent' => $days($row->absent),
            'incomplete' => (int) $row->incomplete, 'late_days' => (int) $row->late_days, 'leave' => $days($row->leave_days),
            'worked' => self::hm($row->worked), 'late' => self::hm($row->late), 'undertime' => self::hm($row->undertime),
            'overtime' => self::hm($row->overtime), 'night' => self::hm($row->night),
        ];
    }
}
