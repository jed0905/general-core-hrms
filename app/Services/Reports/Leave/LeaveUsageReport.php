<?php

namespace App\Services\Reports\Leave;

use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Days used = sum of day_fraction of approved applications' leave dates
 * falling inside the range.
 */
class LeaveUsageReport extends Report
{
    use FiltersEmployees;

    public static function key(): string
    {
        return 'leave-usage';
    }

    public static function category(): string
    {
        return 'leave';
    }

    public function title(): string
    {
        return 'Leave Usage';
    }

    public function description(): string
    {
        return 'Approved leave days taken per employee and leave type within a date range.';
    }

    public function filters(): array
    {
        return [
            ['key' => 'date_from', 'label' => 'Leave from', 'type' => 'date', 'required' => true, 'default' => now()->startOfYear()->toDateString()],
            ['key' => 'date_to', 'label' => 'Leave to', 'type' => 'date', 'required' => true, 'default' => now()->endOfYear()->toDateString()],
            ...$this->employeeFilters(['search', 'department_id', 'include_sub_departments']),
            ['key' => 'leave_type_id', 'label' => 'Leave Type', 'type' => 'select', 'options' => LeaveType::orderBy('name')->get(['id', 'name'])->map(fn ($t) => ['value' => $t->id, 'title' => $t->name])->all()],
        ];
    }

    public function rules(): array
    {
        return array_merge($this->employeeFilterRules(), [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
        ]);
    }

    public function notes(): array
    {
        return ['Approved applications only. Half days and hourly leave count by their day fraction.'];
    }

    public function columns(array $filters = []): array
    {
        return [
            'employee_number' => 'Employee No.',
            'employee' => 'Employee',
            'department' => 'Department',
            'leave_type' => 'Leave Type',
            'dates' => 'Leave Dates',
            'days_used' => 'Days Used',
        ];
    }

    public function sortable(): array
    {
        return ['employee' => 'e.emp_last_name', 'leave_type' => 'lt.name', 'days_used' => 'sum(lad.day_fraction)'];
    }

    protected function tiebreaker(): string|array
    {
        return ['e.id', 'lt.id'];
    }

    public function summary(array $filters): array
    {
        $byType = $this->base($filters)
            ->selectRaw('lt.name as leave_type, sum(lad.day_fraction) as days, count(distinct la.employee_id) as employees')
            ->groupBy('lt.name')
            ->orderBy('lt.name')
            ->get();

        return [[
            'title' => 'Days Used by Leave Type',
            'columns' => ['Leave Type', 'Days Used', 'Employees'],
            'rows' => $byType->map(fn ($r) => [$r->leave_type, round((float) $r->days, 2), (int) $r->employees])->all(),
        ]];
    }

    public function query(array $filters): Builder
    {
        return $this->base($filters)
            ->selectRaw('e.id as employee_id, e.employee_number, e.emp_last_name, e.emp_first_name, e.department_id, lt.id as leave_type_id, lt.name as leave_type, count(*) as dates, sum(lad.day_fraction) as days_used')
            ->groupBy('e.id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id', 'lt.id', 'lt.name')
            ->orderBy('e.emp_last_name')
            ->orderBy('e.id')
            ->orderBy('lt.id');
    }

    private function base(array $filters): Builder
    {
        return $this->applyEmployeeFilters(
            DB::table('leave_application_dates as lad')
                ->join('leave_applications as la', 'la.id', '=', 'lad.leave_application_id')
                ->join('employees as e', 'e.id', '=', 'la.employee_id')
                ->join('leave_types as lt', 'lt.id', '=', 'la.leave_type_id')
                ->where('la.status', LeaveApplication::STATUS_APPROVED)
                ->whereBetween('lad.leave_date', [$filters['date_from'], $filters['date_to']]),
            $filters
        )->when(! empty($filters['leave_type_id']), fn ($q) => $q->where('la.leave_type_id', $filters['leave_type_id']));
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'employee_number' => $row->employee_number,
            'employee' => self::personName($row->emp_last_name, $row->emp_first_name),
            'department' => $this->tree()->path($row->department_id),
            'leave_type' => $row->leave_type,
            'dates' => (int) $row->dates,
            'days_used' => round((float) $row->days_used, 2),
        ];
    }
}
