<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeReportExport implements FromCollection, WithHeadings
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $personal_information_items = $this->request->input('personal_information', []);
        $family_background_items = $this->request->input('family_background', []);
        $job_details_items = $this->request->input('job_details', []);

        $operating_unit_id = $this->request->input('operating_unit_id');
        $department_id = $this->request->input('department_id');
        $job_status_id = $this->request->input('job_status_id');
        $employee_type = $this->request->input('employee_type');

        // Base query
        $query = Employee::query();

        // Filters
        if ($operating_unit_id) $query->where('operating_unit_id', $operating_unit_id);
        if ($department_id) $query->where('department_id', $department_id);
        if ($job_status_id) $query->where('job_status_id', $job_status_id);
        if ($employee_type) $query->where('employee_type', $employee_type);

        // Always include id
        $selectColumns = ['id'];
        $relations = [];

        // Add job details
        if (!empty($job_details_items)) {
            foreach ($job_details_items as $item) {
                switch ($item) {
                    // 🔹 Direct columns
                    case 'employee_number':
                    case 'parenthetical_title':
                    case 'custom_hourly_rate':
                    case 'custom_daily_rate':
                        $selectColumns[] = $item;
                        break;

                    // 🔹 Relationships
                    case 'operating_unit':
                        $relations[] = 'operatingUnit';
                        break;

                    case 'department':
                        $relations[] = 'department';
                        break;

                    case 'detailed_at':
                        $relations[] = 'detailedAt';
                        break;

                    case 'position_title':
                    case 'plantilla_item_number':
                    case 'salary_grade':
                        $relations[] = 'position.government_position';
                        $relations[] = 'position.salary_grade';
                        break;

                    case 'salary_step':
                    case 'amount':
                        $relations[] = 'salaryStep';
                        break;

                    case 'immediate_supervisor':
                        $relations[] = 'immediateSupervisor.personalInformation';
                        break;

                    case 'higher_supervisor':
                        $relations[] = 'higherSupervisor.personalInformation';
                        break;
                }
            }
        }

        // Always include the foreign keys required for relationships
        $foreignKeys = [
            'operating_unit_id',
            'department_id',
            'detailed_at',
            'position_id',
            'salary_step_id',
            'immediate_supervisor_id',
            'higher_supervisor_id',
        ];

        foreach ($foreignKeys as $key) {
            if (!in_array($key, $selectColumns)) {
                $selectColumns[] = $key;
            }
        }

        // Apply select and eager load relationships
        $query->select($selectColumns);

        if (!empty($relations)) {
            $query->with(array_unique($relations));
        }



        // Apply select and eager load
        // $query->select($selectColumns);
        // if (!empty($relations)) {
        //     $query->with(array_unique($relations));
        //     dd($query->get());
        // }

        // Eager load related models
        if (!empty($personal_information_items)) {
            $query->with(['personalInformation:id,employee_id,' . implode(',', $personal_information_items)]);
        }

        if (!empty($family_background_items)) {
            $query->with(['familyBackground:id,employee_id,' . implode(',', $family_background_items)]);
        }

        $employees = $query->get();

        // 🔹 Transform data into flat rows
        return $employees->map(function ($employee) use ($personal_information_items, $family_background_items, $job_details_items) {
            $row = [];

            foreach ($job_details_items as $item) {
                switch ($item) {
                    case 'employee_number':
                        $row[] = $employee->employee_number ?? '';
                        break;
                    case 'operating_unit':
                        $row[] = $employee->operatingUnit->name ?? '';
                        break;
                    case 'department':
                        $row[] = $employee->department->name ?? '';
                        break;
                    case 'detailed_at':
                        $row[] = $employee->detailedAt->name ?? '';
                        break;
                    case 'position_title':
                        $row[] = $employee->position->government_position->name ?? '';
                        break;
                    case 'plantilla_item_number':
                        $row[] = $employee->position->plantilla_item_number ?? '';
                        break;
                    case 'salary_grade':
                        $row[] = $employee->position->salary_grade->salary_grade ?? '';
                        break;
                    case 'salary_step':
                        $row[] = $employee->salaryStep->salary_step_no ?? '';
                        break;
                    case 'amount':
                        $row[] = $employee->salaryStep->amount ?? '';
                        break;
                    case 'custom_hourly_rate':
                        $row[] = $employee->custom_hourly_rate ?? '';
                        break;
                    case 'custom_daily_rate':
                        $row[] = $employee->custom_daily_rate ?? '';
                        break;
                    case 'immediate_supervisor':
                        $row[] = optional(optional($employee->immediateSupervisor)->personalInformation)->getFullNameAttributeAsc() ?? '';
                        break;
                    case 'higher_supervisor':
                        $row[] = optional(optional($employee->higherSupervisor)->personalInformation)->getFullNameAttributeAsc() ?? '';
                        break;
                    default:
                        $row[] = '';
                        break;
                }
            }

            // 🔹 Personal Information
            if ($employee->relationLoaded('personalInformation') && $employee->personalInformation) {
                foreach ($personal_information_items as $col) {
                    $row[] = $employee->personalInformation->$col ?? '';
                }
            } else {
                foreach ($personal_information_items as $col) {
                    $row[] = '';
                }
            }

            // 🔹 Family Background
            if ($employee->relationLoaded('familyBackground') && $employee->familyBackground) {
                foreach ($family_background_items as $col) {
                    $row[] = $employee->familyBackground->$col ?? '';
                }
            } else {
                foreach ($family_background_items as $col) {
                    $row[] = '';
                }
            }
            return $row;
        });
    }

    public function headings(): array
    {
        $personal_information_items = $this->request->input('personal_information', []);
        $family_background_items = $this->request->input('family_background', []);
        $job_details_items = $this->request->input('job_details', []);

        $headings = [];

        foreach ($job_details_items as $col) {
            $headings[] = ucfirst(str_replace('_', ' ', $col));
        }

        foreach ($personal_information_items as $col) {
            $headings[] = ucfirst(str_replace('_', ' ', $col));
        }

        foreach ($family_background_items as $col) {
            $headings[] = ucfirst(str_replace('_', ' ', $col));
        }

        return $headings;
    }
}
