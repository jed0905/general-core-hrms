<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobStatus;
use App\Models\Department;
use App\Models\OperatingUnit;
use Illuminate\Support\Facades\Auth;

class EmployeeReportService
{
    public function index($request)
    {
        // dd($request->operating_unit_id);
        $user = Auth::user();
        $operating_unit_id_of_employee = Employee::where('id', $user->employee_id)->pluck('operating_unit_id')->first();
        $operatingUnits = null;
        $departments = Department::all();
        $jobStatus = JobStatus::all();


        if ($user->hasRole('superadmin') || $user->hasRole('hr_director')) {
            $operatingUnits = OperatingUnit::all();
        } else {
            $operatingUnits = OperatingUnit::where('id', $operating_unit_id_of_employee)->get();
        }

        return [
            'operatingUnits' => $operatingUnits,
            'departments' => $departments,
            'jobStatus' => $jobStatus,
        ];
    }

    public function generateReport($request)
    {
        $operating_unit_id = $request->input('operating_unit_id');
        $department_id = $request->input('department_id');
        $job_status_id = $request->input('job_status_id');
        $employee_type = $request->input('employee_type');

        $personal_information_items = $request->input('personal_information', []);
        $family_background_items = $request->input('family_background', []);
        $job_details_items = $request->input('job_details', []);

        // Start base query
        $query = Employee::query();

        // Apply filters
        if ($operating_unit_id) {
            $query->where('operating_unit_id', $operating_unit_id);
        }
        if ($department_id) {
            $query->where('department_id', $department_id);
        }
        if ($job_status_id) {
            $query->where('job_status_id', $job_status_id);
        }
        if ($employee_type) {
            $query->where('employee_type', $employee_type);
        }

        // Base columns for employee table
        $employeeColumns = [
            'id',
            'operating_unit_id',
            'department_id',
            'job_status_id',
            'employee_type'
        ];

        // Merge job details (must be actual columns from employees table)
        if (!empty($job_details_items)) {
            $employeeColumns = array_merge($employeeColumns, $job_details_items);
        }

        // Apply select once — very important
        $query->select(array_unique($employeeColumns));

        // Eager load relations
        $query->with([
            'personalInformation' => function ($q) use ($personal_information_items) {
                if (!empty($personal_information_items)) {
                    $q->select(array_merge(['employee_id'], $personal_information_items));
                }
            },
            'familyBackground' => function ($q) use ($family_background_items) {
                if (!empty($family_background_items)) {
                    $q->select(array_merge(['employee_id'], $family_background_items));
                }
            },
        ]);

        $employees = $query->get();

        dd($employees->toArray());
    }
}
