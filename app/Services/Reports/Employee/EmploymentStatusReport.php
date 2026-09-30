<?php

namespace App\Services\Reports\Employee;

use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class EmploymentStatusReport extends Report
{
    use FiltersEmployees;

    public static function key(): string
    {
        return 'employment-status';
    }

    public static function category(): string
    {
        return 'employee';
    }

    public function title(): string
    {
        return 'Employment Status';
    }

    public function description(): string
    {
        return 'Employees grouped by the employment statuses configured in the system.';
    }

    public function filters(): array
    {
        return $this->employeeFilters(['employment_status_id', 'department_id', 'include_sub_departments', 'location_id', 'status']);
    }

    public function rules(): array
    {
        return $this->employeeFilterRules();
    }

    public function columns(array $filters = []): array
    {
        return [
            'employment_status' => 'Employment Status',
            'employee_number' => 'Employee No.',
            'name' => 'Name',
            'department' => 'Department',
            'job_title' => 'Job Title',
            'joined_date' => 'Joined',
        ];
    }

    public function sortable(): array
    {
        return ['employment_status' => 'es.name', 'name' => 'e.emp_last_name', 'department' => 'd.name', 'joined_date' => 'e.joined_date'];
    }

    protected function tiebreaker(): string|array
    {
        return 'e.id';
    }

    /**
     * Every configured status appears, including those with no employees.
     */
    public function summary(array $filters): array
    {
        $counts = collect($this->countBy(array_merge($filters, ['employment_status_id' => null]), 'es.name'))->pluck('count', 'label');

        $rows = DB::table('employment_statuses')->orderBy('name')->pluck('name')
            ->map(fn ($name) => ['label' => $name, 'count' => (int) ($counts[$name] ?? 0)]);

        if (isset($counts['Not specified'])) {
            $rows->push(['label' => 'Not specified', 'count' => (int) $counts['Not specified']]);
        }

        return [$this->countSection('Employees by Employment Status', $rows, 'Employment Status')];
    }

    public function query(array $filters): Builder
    {
        return $this->employeeBase($filters)
            ->select(['e.id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id', 'j.job_title', 'es.name as employment_status', 'e.joined_date'])
            ->orderByRaw('es.name is null')
            ->orderBy('es.name')
            ->orderBy('e.emp_last_name')
            ->orderBy('e.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'employment_status' => $row->employment_status ?? 'Not specified',
            'employee_number' => $row->employee_number,
            'name' => self::personName($row->emp_last_name, $row->emp_first_name),
            'department' => $this->tree()->path($row->department_id),
            'job_title' => $row->job_title,
            'joined_date' => $row->joined_date ? substr($row->joined_date, 0, 10) : null,
        ];
    }
}
