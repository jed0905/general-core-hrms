<?php

namespace App\Services;

use App\Http\Resources\EmployeeResource;
use App\Http\Resources\PayrollEmployeeDeductionResource;
use App\Models\Employee;
use App\Models\JobStatus;
use App\Models\PayrollDeduction;
use App\Models\PayrollEmployeeDeduction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class PayrollEmployeeDeductionService
{
 public function getJobStatus(){
  $jobStatus = JobStatus::get();
  return $jobStatus;
 }

 public function getEmployees(Request $request, $perPage = 10){
  $employees = fn() => EmployeeResource::collection(  Employee::with('personalInformation')
    ->where('job_status_id', $request->job_status)
    ->where('employee_type', $request->employee_type)
    ->orderBy(\DB::raw('(SELECT lastname FROM personal_information WHERE personal_information.employee_id = employees.id LIMIT 1)'))
    ->paginate($perPage)->through(function ($employee) use ($request) {
      $employee->view_link = URL::signedRoute('payroll.employee.maintenance.deductions.view', ['id' => $employee->id]);
      return $employee;
    }));

  return $employees;
 }

 public function getEmployeeById($id)
 {
  $employee = Employee::with('personalInformation', 'position.government_position', 'department', 'jobStatus')->findOrFail($id);
  return $employee;
 }

 public function getEmployeeNavigation($currentEmployeeId)
 {
  $employeeStatus = Employee::where('id', $currentEmployeeId)->first();

  // Get all employees ordered by lastname (same as in getEmployees method)
  $allEmployees = Employee::with('personalInformation')
    ->join('personal_information', 'employees.id', '=', 'personal_information.employee_id')
    ->where('job_status_id', $employeeStatus->job_status_id)
    ->where('employee_type', $employeeStatus->employee_type)
    ->orderBy('personal_information.lastname')
    ->select('employees.*')
    ->get();

  $currentIndex = $allEmployees->search(function($employee) use ($currentEmployeeId) {
    return $employee->id == $currentEmployeeId;
  });

  $navigation = [
    'previous' => null,
    'next' => null
  ];
  
  // Helper function to get employee name safely
  $getName = function($employee) {
    if ($employee->personalInformation) {
      $firstName = $employee->personalInformation->firstname ?? '';
      $lastName = $employee->personalInformation->lastname ?? '';
      $suffix = $employee->personalInformation->suffix ?? '';
      $middleInitial = $employee->personalInformation->middlename ? substr($employee->personalInformation->middlename, 0, 1) . '.' : '';
      return trim($lastName . ' ' . $suffix . ', ' . $firstName . ' ' .  $middleInitial);
    }
    return $employee->employee_number ?? 'Unknown Employee';
  };

  
  // Get previous employee
  if ($currentIndex > 0) {
    $previousEmployee = $allEmployees[$currentIndex - 1];
    $navigation['previous'] = [
      'id' => $previousEmployee->id,
      'employee_number' => $previousEmployee->employee_number,
      'name' => $getName($previousEmployee)
    ];
  }

  // Get next employee
  if ($currentIndex < $allEmployees->count() - 1) {
    $nextEmployee = $allEmployees[$currentIndex + 1];
    $navigation['next'] = [
      'id' => $nextEmployee->id,
      'employee_number' => $nextEmployee->employee_number,
      'name' => $getName($nextEmployee)
    ];
  }

  return $navigation;
 }

 public function getEmployeeDeductions($id)
 {
  $employeeDeductions = fn() => PayrollEmployeeDeductionResource::collection(PayrollEmployeeDeduction::with('deduction')
  ->where('employee_number', $id)
  ->paginate(10));
  return $employeeDeductions;
 }

 public function getPayrollDeductions()
 {
  $payrollDeductions = PayrollDeduction::get();
  return $payrollDeductions;
 }

 public function store(array $data)
 {

  $checkDeduction = PayrollEmployeeDeduction::where('employee_number', $data['employee_number'])
  ->where('deduction_id', $data['deduction_id'])
  ->where('date_start', '<=', $data['date_start'])
  ->where('date_end', '>=', $data['date_end'])
  ->where('status', 'active')
  ->first();
  if($checkDeduction){
    throw new \Exception('Employee deduction already exists');
  }

  $payrollEmployeeDeduction = PayrollEmployeeDeduction::create($data);
  return $payrollEmployeeDeduction;
 }

 public function getPayrollEmployeeDeductionById($id)
 {
  $payrollEmployeeDeduction = PayrollEmployeeDeduction::with('deduction')->findOrFail($id);
  return $payrollEmployeeDeduction;
 }

 public function update(int $id, array $data)
 {
  // Check if the record exists
  $existingRecord = PayrollEmployeeDeduction::find($id);
  if (!$existingRecord) {
    throw new \Exception('Employee deduction record not found');
  }

  // Get employee_number from existing record if not in data
  $employeeNumber = $data['employee_number'] ?? $existingRecord->employee_number;

  // Check for overlapping deductions with corrected logic
  $checkDeduction = PayrollEmployeeDeduction::where('employee_number', $employeeNumber)
    ->where('deduction_id', $data['deduction_id'])
    ->where('id', '!=', $id)
    ->where(function($query) use ($data) {
      $query->where(function($q) use ($data) {
        // Check if new period overlaps with existing period
        $q->where('date_start', '<=', $data['date_end'])
          ->where('date_end', '>=', $data['date_start']);
      });
    })
    ->first();
    
  if($checkDeduction){
    throw new \Exception('Employee deduction already exists for this period');
  }

  // Update the record
  $updated = PayrollEmployeeDeduction::where('id', $id)->update([
    'deduction_id' => $data['deduction_id'],
    'amount' => $data['amount'],
    'date_start' => $data['date_start'],
    'date_end' => $data['date_end'],
    'deduction_period' => $data['deduction_period'],
  ]);

  if (!$updated) {
    throw new \Exception('Failed to update employee deduction');
  }

  // Return the updated record
  return PayrollEmployeeDeduction::find($id);
 }

 public function delete($id)
 {
  $payrollEmployeeDeduction = PayrollEmployeeDeduction::findOrFail($id);
  $payrollEmployeeDeduction->delete();
  return $payrollEmployeeDeduction;
 }

}