<?php

namespace App\Services;

use App\Http\Filters\EmployeeFilter;
use App\Http\Resources\EmployeeResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperatingUnit;
use App\Models\PersonalInformation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class SeparatedEmployeesService
{
    public function getEmployees()
    {
        $direction = 'ASC';
        if (request('direction') && request('direction') === 'Descending') {
            $direction = 'DESC';
        }

        $user = Auth::user();
        $operating_unit_id_of_employee = Employee::where('id', $user->employee_id)->pluck('operating_unit_id')->first();

        // Base query with relationships
        $query = Employee::with([
            'personalInformation',
            'department',
            'position',
            'jobStatus',
            'salaryGrade',
            'salaryStep',
            'employeeMovements',
        ]);
        // Apply EmployeeFilter
        $filter = new EmployeeFilter(request()->all());
        $query->whereNotNull('date_separated');
        $query = $filter->apply($query);

        // Restrict if not superadmin/hr_director
        if (! ($user->hasRole('superadmin') || $user->hasRole('hr_director'))) {
            $query->where(function ($q) use ($operating_unit_id_of_employee) {
                $q->where('operating_unit_id', $operating_unit_id_of_employee)
                    ->orWhere('detailed_at', $operating_unit_id_of_employee);
            });
        }

        $query->orderBy(
            PersonalInformation::select('lastname')
                ->whereColumn('employee_id', 'employees.id')
                ->limit(1),
            $direction
        );

        // $size = request()->input('size', 10);

        // Paginate + edit_link
        $employees = $query->paginate(request('size', 10))->through(function ($employee) use ($user) {
            $employee->edit_link = $employee->id === $user->employee_id
                ? route('self-service.my-profile.index')
                : URL::signedRoute('hrmanagement.employee.edit', ['id' => $employee->id]);

            return $employee;
        })->withQueryString();

        return EmployeeResource::collection($employees);
    }

    public function getOperatingUnits()
    {
        if (Auth::user()->hasRole('superadmin') || Auth::user()->hasRole('hr_director')) {
            $operatingUnits = OperatingUnit::get();
        } else {
            $operatingUnits = OperatingUnit::where('id', Auth::user()->employee->operating_unit_id)->get();
        }

        return $operatingUnits;
    }

    public function getDepartmentsByOperatingUnit(string $operating_unit_id)
    {
        $departments = Department::where('operating_unit_id', $operating_unit_id)->get();

        return $departments;
    }
}
