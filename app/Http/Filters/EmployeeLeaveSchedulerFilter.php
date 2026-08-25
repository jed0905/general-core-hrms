<?php

 namespace App\Http\Filters;

use App\Contracts\Filters\Filterable;
use Illuminate\Support\Facades\URL;
use App\Models\EmployeeLeaveScheduler;

 class EmployeeLeaveSchedulerFilter implements Filterable
 {
    public static function get()
    {
     $direction = 'ASC';
     if (request('direction') && request('direction') === 'Descending') {
         $direction = 'DESC';
     }

     $employeeLeaveSchedulers = EmployeeLeaveScheduler::query()
      ->join('employees', 'employee_leave_schedulers.employee_id', '=', 'employees.id')  
      ->when(request()->input('search'), function($query) {
          $query->whereHas('employee.personalInformation', function($query) {
              $query->where('lastname', 'LIKE', "%" . request()->input('search') . "%")
              ->orWhere('firstname', 'LIKE', "%" . request()->input('search') . "%")
              ->orWhere('middlename', 'LIKE', "%" . request()->input('search') . "%")
              ->orWhere('employee_number', 'LIKE', "%" . request()->input('search') . "%");
          });
      })
      ->when(request()->input('job_status_id'), function($query) {
        $query->where('employees.job_status_id', request()->input('job_status_id'));
      })
      ->when(request()->input('employee_type'), function($query) {
        $query->where('employees.employee_type', request()->input('employee_type'));
      })
      ->when(request()->input('leave_type'), function($query) {
          $query->where('leave_id', request()->input('leave_type'));
      })
      ->when(request()->input('operating_unit'), function($query) {
        $query->where('employees.operating_unit_id', request()->input('operating_unit'));
      })
      ->with(['employee.personalInformation', 'employee.jobStatus', 'leave'])
      ->join('personal_information', 'employee_leave_schedulers.employee_id', '=', 'personal_information.employee_id')
      ->orderBy('personal_information.lastname', $direction)
      ->select('employee_leave_schedulers.*')
      ->paginate(request('size', 10))->through(function($employeeLeaveScheduler) {
          $employeeLeaveScheduler->edit_link = Url::signedRoute('hrmanagement.leave.scheduler.edit', ['id' => $employeeLeaveScheduler->id]);
          return $employeeLeaveScheduler;
      })->withQueryString();
        
        // return $query->paginate($size)->withQueryString();
        return $employeeLeaveSchedulers;
    }
 }