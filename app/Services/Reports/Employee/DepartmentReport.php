<?php

namespace App\Services\Reports\Employee;

use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * The organization is the department tree (departments.parent_id); there is
 * no operating-unit level in this HRMS.
 */
class DepartmentReport extends Report
{
    use FiltersEmployees;

    public static function key(): string
    {
        return 'department-organization';
    }

    public static function category(): string
    {
        return 'employee';
    }

    public function title(): string
    {
        return 'Department / Organization';
    }

    public function description(): string
    {
        return 'Employees by department (including sub-departments), job title and supervisor.';
    }

    public function filters(): array
    {
        return $this->employeeFilters(['department_id', 'include_sub_departments', 'status']);
    }

    public function rules(): array
    {
        return $this->employeeFilterRules();
    }

    public function columns(array $filters = []): array
    {
        return [
            'department' => 'Department',
            'job_title' => 'Job Title',
            'employee_number' => 'Employee No.',
            'name' => 'Name',
            'supervisor' => 'Supervisor',
            'employment_status' => 'Employment Status',
        ];
    }

    public function sortable(): array
    {
        return ['department' => 'd.name', 'job_title' => 'j.job_title', 'name' => 'e.emp_last_name'];
    }

    protected function tiebreaker(): string|array
    {
        return 'e.id';
    }

    /**
     * Direct and total (with sub-departments) counts for every department.
     */
    public function summary(array $filters): array
    {
        $direct = $this->employeeBase(array_merge($filters, ['department_id' => null]))
            ->selectRaw('e.department_id, count(*) as count')
            ->groupBy('e.department_id')
            ->pluck('count', 'department_id');

        $departments = empty($filters['department_id'])
            ? $this->tree()->all()->keys()
            : collect($this->tree()->withDescendants((int) $filters['department_id']));

        $rows = $departments
            ->map(fn ($id) => [
                $this->tree()->path($id),
                (int) ($direct[$id] ?? 0),
                collect($this->tree()->withDescendants($id))->sum(fn ($d) => (int) ($direct[$d] ?? 0)),
            ])
            ->sortBy(0)
            ->values();

        if (empty($filters['department_id']) && isset($direct[''])) {
            $rows->push(['No department', (int) $direct[''], (int) $direct['']]);
        }

        return [[
            'title' => 'Employees per Department',
            'columns' => ['Department', 'Directly assigned', 'Including sub-departments'],
            'rows' => $rows->all(),
        ]];
    }

    public function query(array $filters): Builder
    {
        return $this->employeeBase($filters)
            ->leftJoin('employees as s', 's.id', '=', 'e.supervisor_id')
            ->select(['e.id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id', 'j.job_title', 'es.name as employment_status', 's.emp_last_name as supervisor_last', 's.emp_first_name as supervisor_first'])
            ->orderByRaw('d.name is null')
            ->orderBy('d.name')
            ->orderBy('j.job_title')
            ->orderBy('e.emp_last_name')
            ->orderBy('e.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'department' => $this->tree()->path($row->department_id) ?? 'No department',
            'job_title' => $row->job_title ?? 'Not specified',
            'employee_number' => $row->employee_number,
            'name' => self::personName($row->emp_last_name, $row->emp_first_name),
            'supervisor' => self::personName($row->supervisor_last, $row->supervisor_first),
            'employment_status' => $row->employment_status,
        ];
    }
}
