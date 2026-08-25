<?php

 namespace App\Services;

use App\Models\JobStatus;
use App\Models\PayrollCalendar;

 Class PayrollCalendarService
 {
  public function getPayrollCalendars()
  {
    $payrollCalendar = PayrollCalendar::paginate(10);
    return $payrollCalendar;
  }

  public function getJobStatus()
  {
   $job_status = JobStatus::get();
   return $job_status;
  }
 }