<?php

 namespace App\Helpers;

use App\Models\Employee;
use Illuminate\Support\Facades\Auth;

 class SupervisorHelper
 {
  public static function isDirectSupervisor()
  {
    $user = Auth::user();
    $employee = Employee::where('id', $user->employee_id)->first();
    if (!$employee) {
      return false;
    }
    return $employee->immediate_supervisor_id === $user->employee_id;
  }
 }