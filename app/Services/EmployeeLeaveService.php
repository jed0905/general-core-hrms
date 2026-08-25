<?php

 namespace App\Services;

use App\Http\Filters\EmployeeFilter;
use App\Http\Resources\EmployeeTableResource;
use App\Models\Employee;
use App\Models\JobStatus;
use App\Models\OperatingUnit;
use App\Models\PersonalInformation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

 class EmployeeLeaveService
 {
  public function index()
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
          'operatingUnit'
          
      ]);

      $filter = new EmployeeFilter(request()->all());
      $query = $filter->apply($query);

      // Restrict if not superadmin/hr_director
      if (!($user->hasRole('superadmin') || $user->hasRole('hr_director'))) {
          $query->where('operating_unit_id', $operating_unit_id_of_employee);
      }

      $query->orderBy(
          PersonalInformation::select('lastname')
          ->whereColumn('employee_id', 'employees.id')
          ->limit(1)
          ->limit(1),
          $direction
      );

      // Paginate + edit_link
      $employees = $query->paginate(request()->input('size', 10))->through(function ($employee) use ($user) {
          $employee->edit_link = $employee->id === $user->employee_id
              ? route('self-service.my-profile.index')
              : URL::signedRoute('hrmanagement.employee.edit', ['id' => $employee->id]);

          return $employee;
      })->withQueryString();


    $employees = EmployeeTableResource::collection($employees);
    return $employees;
  }

  public function getOperatingUnits(){
    $operatingUnits = OperatingUnit::get();
    return $operatingUnits;
  }

  public function employementStatus(){
    $employementStatus = JobStatus::get();
    
    return $employementStatus;
  }
 }