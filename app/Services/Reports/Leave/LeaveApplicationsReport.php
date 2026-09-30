<?php

namespace App\Services\Reports\Leave;

use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeaveApplicationsReport extends Report
{
    use FiltersEmployees;

    private const STATUSES = [
        LeaveApplication::STATUS_PENDING,
        LeaveApplication::STATUS_APPROVED,
        LeaveApplication::STATUS_REJECTED,
        LeaveApplication::STATUS_RETURNED,
        LeaveApplication::STATUS_CANCELLED,
    ];

    public static function key(): string
    {
        return 'leave-applications';
    }

    public static function category(): string
    {
        return 'leave';
    }

    public function title(): string
    {
        return 'Leave Applications';
    }

    public function description(): string
    {
        return 'Leave applications filed within a date range, with their dates, days and status.';
    }

    public function filters(): array
    {
        return [
            ['key' => 'filed_from', 'label' => 'Filed from', 'type' => 'date', 'default' => now()->startOfYear()->toDateString()],
            ['key' => 'filed_to', 'label' => 'Filed to', 'type' => 'date', 'default' => now()->toDateString()],
            ...$this->employeeFilters(['search', 'department_id', 'include_sub_departments']),
            ['key' => 'leave_type_id', 'label' => 'Leave Type', 'type' => 'select', 'options' => LeaveType::orderBy('name')->get(['id', 'name'])->map(fn ($t) => ['value' => $t->id, 'title' => $t->name])->all()],
            ['key' => 'application_status', 'label' => 'Status', 'type' => 'select', 'options' => collect(self::STATUSES)->map(fn ($s) => ['value' => $s, 'title' => ucfirst($s)])->all()],
        ];
    }

    public function rules(): array
    {
        return array_merge($this->employeeFilterRules(), [
            'filed_from' => ['nullable', 'date_format:Y-m-d'],
            'filed_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filed_from'],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'application_status' => ['nullable', Rule::in(self::STATUSES)],
        ]);
    }

    public function columns(array $filters = []): array
    {
        return [
            'filed' => 'Filed',
            'employee_number' => 'Employee No.',
            'employee' => 'Employee',
            'department' => 'Department',
            'leave_type' => 'Leave Type',
            'first_date' => 'First Date',
            'last_date' => 'Last Date',
            'days' => 'Days',
            'status' => 'Status',
        ];
    }

    public function sortable(): array
    {
        return ['filed' => 'la.submitted_at', 'employee' => 'e.emp_last_name', 'leave_type' => 'lt.name', 'days' => 'la.total_days', 'status' => 'la.status'];
    }

    protected function tiebreaker(): string|array
    {
        return 'la.id';
    }

    public function summary(array $filters): array
    {
        $counts = $this->base($filters)
            ->selectRaw('la.status, count(*) as count, sum(la.total_days) as days')
            ->groupBy('la.status')
            ->get()
            ->keyBy('status');

        return [[
            'title' => 'Applications by Status',
            'columns' => ['Status', 'Applications', 'Days'],
            'rows' => collect(self::STATUSES)
                ->filter(fn ($s) => isset($counts[$s]))
                ->map(fn ($s) => [ucfirst($s), (int) $counts[$s]->count, round((float) $counts[$s]->days, 2)])
                ->values()
                ->all(),
        ]];
    }

    public function query(array $filters): Builder
    {
        return $this->base($filters)
            ->select(['la.id', 'la.submitted_at', 'la.total_days', 'la.status', 'lt.name as leave_type', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id'])
            ->selectSub(DB::table('leave_application_dates')->whereColumn('leave_application_id', 'la.id')->selectRaw('min(leave_date)'), 'first_date')
            ->selectSub(DB::table('leave_application_dates')->whereColumn('leave_application_id', 'la.id')->selectRaw('max(leave_date)'), 'last_date')
            ->orderByDesc('la.submitted_at')
            ->orderByDesc('la.id');
    }

    private function base(array $filters): Builder
    {
        return $this->applyEmployeeFilters(
            DB::table('leave_applications as la')
                ->join('employees as e', 'e.id', '=', 'la.employee_id')
                ->join('leave_types as lt', 'lt.id', '=', 'la.leave_type_id'),
            $filters
        )
            ->when(! empty($filters['filed_from']), fn ($q) => $q->whereDate('la.submitted_at', '>=', $filters['filed_from']))
            ->when(! empty($filters['filed_to']), fn ($q) => $q->whereDate('la.submitted_at', '<=', $filters['filed_to']))
            ->when(! empty($filters['leave_type_id']), fn ($q) => $q->where('la.leave_type_id', $filters['leave_type_id']))
            ->when(! empty($filters['application_status']), fn ($q) => $q->where('la.status', $filters['application_status']));
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'filed' => substr((string) $row->submitted_at, 0, 10),
            'employee_number' => $row->employee_number,
            'employee' => self::personName($row->emp_last_name, $row->emp_first_name),
            'department' => $this->tree()->path($row->department_id),
            'leave_type' => $row->leave_type,
            'first_date' => $row->first_date ? substr((string) $row->first_date, 0, 10) : null,
            'last_date' => $row->last_date ? substr((string) $row->last_date, 0, 10) : null,
            'days' => (float) $row->total_days,
            'status' => ucfirst($row->status),
        ];
    }
}
