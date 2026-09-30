<?php

namespace App\Services\Reports\Employee;

use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Based on employees.joined_date only; created_at is never used as a hire date.
 */
class NewHiresReport extends Report
{
    use FiltersEmployees;

    public static function key(): string
    {
        return 'new-hires';
    }

    public static function category(): string
    {
        return 'employee';
    }

    public function title(): string
    {
        return 'New Hires';
    }

    public function description(): string
    {
        return 'Employees who joined within a date range.';
    }

    public function filters(): array
    {
        return array_map(function ($filter) {
            if (in_array($filter['key'], ['joined_from', 'joined_to'], true)) {
                $filter['required'] = true;
                $filter['default'] = $filter['key'] === 'joined_from' ? now()->startOfMonth()->toDateString() : now()->toDateString();
            }

            return $filter;
        }, $this->employeeFilters(['joined_from', 'joined_to', 'department_id', 'include_sub_departments', 'employment_status_id', 'location_id'], ['status' => 'all']));
    }

    public function rules(): array
    {
        return array_merge($this->employeeFilterRules(), [
            'joined_from' => ['nullable', 'date_format:Y-m-d'],
            'joined_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joined_from'],
        ]);
    }

    public function columns(array $filters = []): array
    {
        return [
            'joined_date' => 'Joined',
            'employee_number' => 'Employee No.',
            'name' => 'Name',
            'department' => 'Department',
            'job_title' => 'Job Title',
            'employment_status' => 'Employment Status',
            'status' => 'Record Status',
        ];
    }

    public function sortable(): array
    {
        return ['joined_date' => 'e.joined_date', 'name' => 'e.emp_last_name', 'department' => 'd.name'];
    }

    protected function tiebreaker(): string|array
    {
        return 'e.id';
    }

    public function notes(): array
    {
        $missing = $this->applyEmployeeFilters(DB::table('employees as e'), ['status' => 'active'])->whereNull('e.joined_date')->count();

        return $missing > 0
            ? ["{$missing} active employee(s) have no joined date recorded and can't appear in this report."]
            : [];
    }

    public function summary(array $filters): array
    {
        return [$this->countSection('New Hires by Department', $this->countBy($filters, 'd.name'), 'Department')];
    }

    public function query(array $filters): Builder
    {
        return $this->employeeBase($filters)
            ->whereNotNull('e.joined_date')
            ->select(['e.id', 'e.joined_date', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id', 'j.job_title', 'es.name as employment_status', 'e.status'])
            ->orderByDesc('e.joined_date')
            ->orderBy('e.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'joined_date' => substr((string) $row->joined_date, 0, 10),
            'employee_number' => $row->employee_number,
            'name' => self::personName($row->emp_last_name, $row->emp_first_name),
            'department' => $this->tree()->path($row->department_id),
            'job_title' => $row->job_title,
            'employment_status' => $row->employment_status,
            'status' => $row->status,
        ];
    }
}
