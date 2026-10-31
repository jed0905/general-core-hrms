<?php

namespace App\Services\Reports\Attendance;

use App\Models\AttendanceDay;
use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Calculated daily attendance (the attendance module's result, not raw punches). */
class DailyAttendanceReport extends Report
{
    use FiltersEmployees;
    use ScopesAttendance;

    public static function key(): string
    {
        return 'attendance-daily';
    }

    public static function category(): string
    {
        return 'attendance';
    }

    public function title(): string
    {
        return 'Daily Attendance';
    }

    public function description(): string
    {
        return 'Per employee per day: schedule, worked time, lateness, undertime, overtime, night time and status.';
    }

    public function filters(): array
    {
        return [
            ['key' => 'date_from', 'label' => 'From', 'type' => 'date', 'required' => true, 'default' => now()->startOfMonth()->toDateString()],
            ['key' => 'date_to', 'label' => 'To', 'type' => 'date', 'required' => true, 'default' => now()->toDateString()],
            ...$this->employeeFilters(['search', 'department_id', 'include_sub_departments', 'location_id']),
            ['key' => 'attendance_status', 'label' => 'Attendance status', 'type' => 'select', 'options' => [
                ['value' => 'exceptions', 'title' => 'Exceptions (missing punch, absent, late)'],
                ...array_map(fn ($s) => ['value' => $s, 'title' => ucfirst(str_replace('_', ' ', $s))], AttendanceDay::STATUSES),
            ]],
        ];
    }

    public function rules(): array
    {
        return [...$this->employeeFilterRules(),
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'attendance_status' => ['nullable', Rule::in(['exceptions', ...AttendanceDay::STATUSES])],
        ];
    }

    public function notes(): array
    {
        return ['Times in hours:minutes. Overtime is the approved overtime. Only employees you may see are included.'];
    }

    public function columns(array $filters = []): array
    {
        return [
            'date' => 'Date', 'employee_number' => 'Employee No.', 'employee' => 'Employee', 'department' => 'Department',
            'day_type' => 'Day', 'shift' => 'Shift', 'worked' => 'Worked', 'late' => 'Late', 'undertime' => 'Undertime',
            'overtime' => 'Overtime', 'night' => 'Night', 'leave' => 'Leave (days)', 'status' => 'Status',
        ];
    }

    public function sortable(): array
    {
        return ['date' => 'd.work_date', 'employee' => 'e.emp_last_name'];
    }

    protected function tiebreaker(): string|array
    {
        return 'd.id';
    }

    public function summary(array $filters): array
    {
        $rows = $this->base($filters)->selectRaw('d.status, count(*) as total')->groupBy('d.status')->orderBy('d.status')->get();

        return [[
            'title' => 'Days by Status',
            'columns' => ['Status', 'Days'],
            'rows' => $rows->map(fn ($r) => [ucfirst(str_replace('_', ' ', $r->status)), (int) $r->total])->all(),
        ]];
    }

    public function query(array $filters): Builder
    {
        return $this->base($filters)
            ->leftJoin('shifts as s', 's.id', '=', 'd.shift_id')
            ->select(['d.*', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id', 's.code as shift_code'])
            ->orderBy('d.work_date')
            ->orderBy('e.emp_last_name')
            ->orderBy('d.id');
    }

    private function base(array $filters): Builder
    {
        $query = $this->applyEmployeeFilters(
            DB::table('attendance_days as d')->join('employees as e', 'e.id', '=', 'd.employee_id')
                ->whereBetween('d.work_date', [$filters['date_from'], $filters['date_to']]),
            $filters
        );

        return $this->scopeEmployees($query)
            ->when(($filters['attendance_status'] ?? null) === 'exceptions', fn ($q) => $q->whereIn('d.status', ['incomplete', 'absent', 'late']))
            ->when(! in_array($filters['attendance_status'] ?? null, [null, 'exceptions'], true), fn ($q) => $q->where('d.status', $filters['attendance_status']));
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'date' => substr($row->work_date, 0, 10), 'employee_number' => $row->employee_number,
            'employee' => self::personName($row->emp_last_name, $row->emp_first_name), 'department' => $this->tree()->path($row->department_id),
            'day_type' => str_replace('_', ' ', $row->day_type).($row->holiday_type ? " ({$row->holiday_type})" : ''), 'shift' => $row->shift_code,
            'worked' => self::hm($row->worked_minutes), 'late' => self::hm($row->late_minutes), 'undertime' => self::hm($row->undertime_minutes),
            'overtime' => self::hm($row->approved_overtime_minutes), 'night' => self::hm($row->night_minutes),
            'leave' => (float) $row->leave_days ? rtrim(rtrim((string) $row->leave_days, '0'), '.') : '', 'status' => str_replace('_', ' ', $row->status),
        ];
    }
}
