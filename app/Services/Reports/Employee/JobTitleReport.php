<?php

namespace App\Services\Reports\Employee;

use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Job titles aren't tied to departments in this HRMS; the departments shown
 * are those of the employees who currently hold each title.
 */
class JobTitleReport extends Report
{
    use FiltersEmployees;

    public static function key(): string
    {
        return 'job-title';
    }

    public static function category(): string
    {
        return 'employee';
    }

    public function title(): string
    {
        return 'Job Title';
    }

    public function description(): string
    {
        return 'Employees per job title, and the departments where each title is held.';
    }

    public function filters(): array
    {
        return $this->employeeFilters(['job_title_id', 'department_id', 'include_sub_departments', 'status']);
    }

    public function rules(): array
    {
        return $this->employeeFilterRules();
    }

    public function columns(array $filters = []): array
    {
        return [
            'job_title' => 'Job Title',
            'employee_number' => 'Employee No.',
            'name' => 'Name',
            'department' => 'Department',
            'employment_status' => 'Employment Status',
        ];
    }

    public function sortable(): array
    {
        return ['job_title' => 'j.job_title', 'name' => 'e.emp_last_name', 'department' => 'd.name'];
    }

    protected function tiebreaker(): string|array
    {
        return 'e.id';
    }

    public function summary(array $filters): array
    {
        $groups = $this->employeeBase($filters)
            ->selectRaw('j.job_title as title, e.department_id, count(*) as count')
            ->groupBy('j.job_title', 'e.department_id')
            ->get()
            ->groupBy(fn ($r) => $r->title ?? 'Not specified');

        $rows = $groups->map(fn ($byDepartment, $title) => [
            $title,
            $byDepartment->sum('count'),
            $byDepartment->map(fn ($r) => $this->tree()->path($r->department_id) ?? 'No department')->unique()->sort()->implode('; '),
        ])->sortBy(0)->values()->all();

        return [[
            'title' => 'Employees per Job Title',
            'columns' => ['Job Title', 'Employees', 'Department(s)'],
            'rows' => $rows,
        ]];
    }

    public function query(array $filters): Builder
    {
        return $this->employeeBase($filters)
            ->select(['e.id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id', 'j.job_title', 'es.name as employment_status'])
            ->orderByRaw('j.job_title is null')
            ->orderBy('j.job_title')
            ->orderBy('e.emp_last_name')
            ->orderBy('e.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'job_title' => $row->job_title ?? 'Not specified',
            'employee_number' => $row->employee_number,
            'name' => self::personName($row->emp_last_name, $row->emp_first_name),
            'department' => $this->tree()->path($row->department_id),
            'employment_status' => $row->employment_status,
        ];
    }
}
