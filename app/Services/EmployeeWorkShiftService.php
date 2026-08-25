<?php

namespace App\Services;

use App\Http\Resources\EmployeeResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobStatus;
use App\Models\OperatingUnit;
use App\Models\WeeklyShiftTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class EmployeeWorkShiftService
{
    public function getEmployees(){
        $user = Auth::user();
        $employee = $user->employee;
        
        $query = Employee::with(['personalInformation', 'department']);
        
        // Role-based filtering
        if (!($user->hasRole('superadmin') || $user->hasRole('hr_director'))) {
            // For other roles, filter by operating unit
            $operating_unit_id = $employee->operating_unit_id ?? null;
            if ($operating_unit_id) {
                $query->where('operating_unit_id', $operating_unit_id);
            }
        }
        // If superadmin or hr_director, get all employees (no additional filtering)
        
        $employees = $query->get();
        return EmployeeResource::collection($employees);
    }

    public function getEmployeesPaginated(){
        $direction = 'ASC';

        if(request('direction') && request('direction') === 'Descending') {
            $direction = 'DESC';
        }

        $user = Auth::user();
        $employee = $user->employee;
        
        $query = Employee::with(['personalInformation', 'department', 'weeklyShiftTemplate', 'jobStatus', 'operatingUnit']);
        
        // Role-based filtering
        if (!($user->hasRole('superadmin') || $user->hasRole('hr_director'))) {
            // For other roles, filter by operating unit
            $operating_unit_id = $employee->operating_unit_id ?? null;
            if ($operating_unit_id) {
                $query->where('operating_unit_id', $operating_unit_id);
            }
        }

        $query->when(request('search'), function($q) {
            $q->whereHas('personalInformation', function($q) {
                $q->where('firstname', 'like', "%".request('search')."%")
                  ->orWhere('lastname', 'like', "%".request('search')."%")
                  ->orWhere('middlename', 'like', "%".request('search')."%");
            })->orWhere('employee_number', 'like', "%".request('search')."%");
        });

        $query->when(request('operating_unit'), function($q) {
            $q->where('operating_unit_id', request('operating_unit'));
        });

        $query->when(request('department'), function($q) {
            $q->where('department_id', request('department'));
        });
        
        $query->when(request('employee_status'), function($q) {
            $q->where('job_status_id', request('employee_status'));
        });

        $query->when(request('employee_type'), function($q) {
            $q->where('employee_type', request('employee_type'));
        });

        
       
        
        // Join with personal_information for ordering
        $query->join('personal_information', 'employees.id', '=', 'personal_information.employee_id');
        
        
        $query->orderBy('personal_information.lastname', $direction);
        
        // Select only employee columns
        $query->select('employees.*');
        
  
        
        $employees = $query->paginate(request('size', 10));
        
        return EmployeeResource::collection($employees);
    }

    public function getOperatingUnits(){
        $user = Auth::user();
        
        // Role-based filtering for operating units
        if ($user->hasRole('superadmin') || $user->hasRole('hr_director')) {
            // Superadmin and HR Director can see all operating units
            $operating_units = OperatingUnit::get();
        } else {
            // Other roles can only see their own operating unit
            $operating_unit_id = $user->employee->operating_unit_id ?? null;
            if ($operating_unit_id) {
                $operating_units = OperatingUnit::where('id', $operating_unit_id)->get();
            } else {
                $operating_units = collect(); // Empty collection if no operating unit
            }
        }
        
        return $operating_units;
    }

    public function getEmployeeStatus(){
        $employee_status = JobStatus::get();
        return $employee_status;
    }

    public function getDepartments(){
        $user = Auth::user();
        
        // Role-based filtering for departments
        if ($user->hasRole('superadmin') || $user->hasRole('hr_director')) {
            // Superadmin and HR Director can see all departments
            $departments = Department::get();
        } else {
            // Other roles can only see departments from their operating unit
            $operating_unit_id = $user->employee->operating_unit_id ?? null;
            if ($operating_unit_id) {
                $departments = Department::where('operating_unit_id', $operating_unit_id)->get();
            } else {
                $departments = collect(); // Empty collection if no operating unit
            }
        }
        
        return $departments;
    }

    public function getDepartmentsByOperatingUnit($operatingUnitId){    
        $department = Department::where('operating_unit_id', $operatingUnitId)->get();
        return $department;
    }

    public function assignWorkShift($employeeId, $workShiftId){
        $employeeWorkShift = Employee::find($employeeId);
        $employeeWorkShift->work_shift = $workShiftId;
        $employeeWorkShift->save();
        return $employeeWorkShift;
    }

    public function getEmployeesWithoutWorkShift(){
        
        $user = Auth::user();
        $employee = $user->employee;
        
        $query = Employee::with(['personalInformation', 'department', 'operatingUnit', 'jobStatus'])
            ->whereNull('work_shift');
        
        // Role-based filtering
        if (!($user->hasRole('superadmin') || $user->hasRole('hr_director'))) {
            // For other roles, filter by operating unit
            $operating_unit_id = $employee->operating_unit_id ?? null;
            if ($operating_unit_id) {
                $query->where('operating_unit_id', $operating_unit_id);
            }
        }
        // ✅ Determine if filters are actually applied
        $hasFilter = collect([
            request('operating_unit'),
            request('department'),
            request('employee_status'),
            request('employee_type'),
        ])->filter(function ($value) {
            // Consider null, empty string, and "all" as no filter
            return !is_null($value) && $value !== '' && $value !== 'all';
        })->isNotEmpty();

        // ✅ If no filters applied, return empty collection
        if (!$hasFilter) {
            return EmployeeResource::collection(collect([]));
        }

        
        $query->when(request('operating_unit'), function($q) {
            $q->where('operating_unit_id', request('operating_unit'));
        });

        $query->when(request('department'), function($q) {
            $q->where('department_id', request('department'));
        });

        $query->when(request('employee_status'), function($q) {
            $q->where('job_status_id', request('employee_status'));
        });

        $query->when(request('employee_type'), function($q) {
            $q->where('employee_type', request('employee_type'));
        });

        // Join with personal_information for ordering
        $query->join('personal_information', 'employees.id', '=', 'personal_information.employee_id');
        $query->orderBy('personal_information.lastname', 'ASC');
        $query->select('employees.*');
        
        $employees = $query->get();
       

        return EmployeeResource::collection($employees);
    }

    public function getEmployeesWithWeeklyShiftTemplate($weeklyShiftTemplateId){
        $user = Auth::user();
        $employee = $user->employee;
        
        $query = Employee::with(['personalInformation', 'department', 'operatingUnit', 'jobStatus'])
            ->where('work_shift', $weeklyShiftTemplateId);
        
        // Role-based filtering
        if (!($user->hasRole('superadmin') || $user->hasRole('hr_director'))) {
            // For other roles, filter by operating unit
            $operating_unit_id = $employee->operating_unit_id ?? null;
            if ($operating_unit_id) {
                $query->where('operating_unit_id', $operating_unit_id);
            }
        }

        $query->when(request('operating_unit'), function($q) {
            $q->where('operating_unit_id', request('operating_unit'));
        });

        $query->when(request('department'), function($q) {
            $q->where('department_id', request('department'));
        });

        $query->when(request('employee_status'), function($q) {
            $q->where('job_status_id', request('employee_status'));
        });

        $query->when(request('employee_type'), function($q) {
            $q->where('employee_type', request('employee_type'));
        });

        // Join with personal_information for ordering
        $query->join('personal_information', 'employees.id', '=', 'personal_information.employee_id');
        $query->orderBy('personal_information.lastname', 'ASC');
        $query->select('employees.*');
        
        $employees = $query->get();
        return EmployeeResource::collection($employees);
    }

    public function getWeeklyShiftTemplates(){
        return WeeklyShiftTemplate::get();
    }

    public function getWeeklyShiftTemplateById($id){
        return WeeklyShiftTemplate::with('days.dailyShiftSchedule')->where('id', $id)->first();
    }

    public function assignEmployeesToWorkShift($employeeIds, $weeklyShiftTemplateId){
        return Employee::whereIn('id', $employeeIds)
            ->update(['work_shift' => $weeklyShiftTemplateId]);
    }

    public function removeEmployeesFromWorkShift($employeeIds){
        return Employee::whereIn('id', $employeeIds)
            ->update(['work_shift' => null]);
    }


}