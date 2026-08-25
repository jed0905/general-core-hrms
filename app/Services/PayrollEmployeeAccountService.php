<?php

namespace App\Services;

use App\Http\Resources\EmployeeResource;
use App\Http\Resources\PayrollEmployeeAccountInformationResource;
use App\Models\Employee;
use App\Models\JobStatus;
use App\Models\PayrollAccountType;
use App\Models\PayrollEmployeeAccount;
use App\Models\PayrollEmployeeAccountInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PayrollEmployeeAccountService
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

    public function getEmployeeAccounts($id)
    {
        $employeeAccounts = fn() => PayrollEmployeeAccountInformationResource::collection(
            PayrollEmployeeAccountInformation::with('accountType')
            ->where('employee_number', $id)
            ->paginate(10)
        );
        return $employeeAccounts;
    }

    public function getAccountTypes()
    {
        $accountTypes = PayrollAccountType::get();
        return $accountTypes;
    }

    public function storeEmployeeAccount($request)
    {
        $employeeAccount = PayrollEmployeeAccountInformation::create($request->all());
        return $employeeAccount;
    }

    public function getEmployeeAccountById($id)
    {
        $employeeAccount = PayrollEmployeeAccountInformation::findOrFail($id);
        $employee = Employee::with('personalInformation', 'position.government_position', 'department', 'jobStatus')->findOrFail($employeeAccount->employee_number);
        return [
            'employeeAccount' => $employeeAccount, 
            'employee' => $employee
        ];
    }

    public function updateEmployeeAccount($id, $request){
        

        $checkIfExists = PayrollEmployeeAccountInformation::where('account_type_id', $request['account_type_id'])
        ->where('account_number', $request['account_number'])
        ->where('id', '!=', $id)
        ->first();

        if($checkIfExists){
            throw new \Exception('Employee account already exists');

        }
        
        $employeeAccount = PayrollEmployeeAccountInformation::findOrFail($id);
        $employeeAccount->update($request->all());
        return $employeeAccount;

    }

    public function deleteEmployeeAccount($id)
    {
        $employeeAccount = PayrollEmployeeAccountInformation::findOrFail($id);
        $employeeAccount->delete();
        return $employeeAccount;
    }


}