<?php

namespace App\Services\Reports\Employee;

use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;

class EmployeeMasterlistReport extends Report
{
    use FiltersEmployees;

    public static function key(): string
    {
        return 'employee-masterlist';
    }

    public static function category(): string
    {
        return 'employee';
    }

    public function title(): string
    {
        return 'Employee Masterlist';
    }

    public function description(): string
    {
        return 'All employees with their current assignment and contact details.';
    }

    public function filters(): array
    {
        return $this->employeeFilters(['search', 'department_id', 'include_sub_departments', 'job_title_id', 'employment_status_id', 'location_id', 'sex', 'supervisor_id', 'status', 'joined_from', 'joined_to']);
    }

    public function rules(): array
    {
        return $this->employeeFilterRules();
    }

    public function columns(array $filters = []): array
    {
        return [
            'employee_number' => 'Employee No.',
            'name' => 'Name',
            'sex' => 'Sex',
            'department' => 'Department',
            'job_title' => 'Job Title',
            'employment_status' => 'Employment Status',
            'location' => 'Location',
            'supervisor' => 'Supervisor',
            'joined_date' => 'Joined',
            'status' => 'Record Status',
            'work_email' => 'Work Email',
            'phone' => 'Phone',
        ];
    }

    public function sortable(): array
    {
        return [
            'employee_number' => 'e.employee_number',
            'name' => 'e.emp_last_name',
            'department' => 'd.name',
            'job_title' => 'j.job_title',
            'employment_status' => 'es.name',
            'joined_date' => 'e.joined_date',
            'status' => 'e.status',
        ];
    }

    protected function tiebreaker(): string|array
    {
        return 'e.id';
    }

    public function query(array $filters): Builder
    {
        return $this->employeeBase($filters)
            ->leftJoin('employees as s', 's.id', '=', 'e.supervisor_id')
            ->select([
                'e.id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.emp_middle_name',
                'e.emp_sex', 'e.department_id', 'j.job_title', 'es.name as employment_status',
                'l.address as location_address', 'l.city as location_city',
                's.emp_last_name as supervisor_last', 's.emp_first_name as supervisor_first',
                'e.joined_date', 'e.status', 'e.work_email', 'e.mobile_no', 'e.work_no',
            ])
            ->orderBy('e.emp_last_name')
            ->orderBy('e.emp_first_name')
            ->orderBy('e.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'employee_number' => $row->employee_number,
            'name' => trim(self::personName($row->emp_last_name, $row->emp_first_name).($row->emp_middle_name ? " {$row->emp_middle_name}" : '')),
            'sex' => $row->emp_sex ? ucfirst($row->emp_sex) : null,
            'department' => $this->tree()->path($row->department_id),
            'job_title' => $row->job_title,
            'employment_status' => $row->employment_status,
            'location' => self::locationLabel($row->location_address, $row->location_city),
            'supervisor' => self::personName($row->supervisor_last, $row->supervisor_first),
            'joined_date' => $row->joined_date ? substr($row->joined_date, 0, 10) : null,
            'status' => $row->status,
            'work_email' => $row->work_email,
            'phone' => $row->mobile_no ?: $row->work_no,
        ];
    }
}
