<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayrollEmployeeAccountRequest;
use App\Http\Requests\UpdatePayrollEmployeeAccountRequest;
use App\Models\Employee;
use App\Models\JobStatus;
use App\Models\PayrollAccountType;
use App\Services\PayrollEmployeeAccountService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollEmployeeAccountController extends Controller
{
    public function index(Request $request, PayrollEmployeeAccountService $payrollEmployeeAccountService){
        $jobStatus = $payrollEmployeeAccountService->getJobStatus();
        
        $employees = [];
        if ($request !== null && $request->job_status && $request->employee_type) {
            $perPage = $request->get('per_page', 10);
            $employees = $payrollEmployeeAccountService->getEmployees($request, $perPage);
        }
        
        return Inertia::render('app/Payroll/EmployeeMaintenance/EmpoyeeAccounts/Index', [
            'jobStatus' => $jobStatus,
            'employees' => $employees
        ]);
    }

    public function view($id, PayrollEmployeeAccountService $payrollEmployeeAccountService)
    {
      
        $employee = $payrollEmployeeAccountService->getEmployeeById($id);
        $employeeAccounts = $payrollEmployeeAccountService->getEmployeeAccounts($id);
        $navigation = $payrollEmployeeAccountService->getEmployeeNavigation($id);
        
        return Inertia::render('app/Payroll/EmployeeMaintenance/EmpoyeeAccounts/View', [
            'employee' => $employee,
            'employeeAccounts' => $employeeAccounts,
            'navigation' => $navigation
        ]);
    }

    public function create($id, PayrollEmployeeAccountService $payrollEmployeeAccountService)
    {
        $employee = $payrollEmployeeAccountService->getEmployeeById($id);
        $accountTypes = $payrollEmployeeAccountService->getAccountTypes();
        
        return Inertia::render('app/Payroll/EmployeeMaintenance/EmpoyeeAccounts/Create', [
            'employee' => $employee,
            'accountTypes' => $accountTypes
        ]);
    }

    public function store(StorePayrollEmployeeAccountRequest  $request, PayrollEmployeeAccountService $payrollEmployeeAccountService)
    {   
        try{
            $employeeAccount = $payrollEmployeeAccountService->storeEmployeeAccount($request);
            return redirect()->route('payroll.employee.maintenance.accounts.create', ['id' => $request->employee_number]);
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function edit($id, PayrollEmployeeAccountService $payrollEmployeeAccountService)
    {
        $employeeAccount = $payrollEmployeeAccountService->getEmployeeAccountById($id);
        $accountTypes = $payrollEmployeeAccountService->getAccountTypes();
        // dd($employeeAccount);
        return Inertia::render('app/Payroll/EmployeeMaintenance/EmpoyeeAccounts/Edit', [
            'employeeAccount' => $employeeAccount['employeeAccount'],
            'accountTypes' => $accountTypes,
            'employee' => $employeeAccount['employee']
        ]);
    }

    public function update($id, UpdatePayrollEmployeeAccountRequest $request, PayrollEmployeeAccountService $payrollEmployeeAccountService){
        try{
            $payrollEmployeeAccountService->updateEmployeeAccount($id, $request);
            return redirect()->route('payroll.employee.maintenance.accounts.view', ['id' => $request->employee_number]);
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function delete($id, PayrollEmployeeAccountService $payrollEmployeeAccountService){
        try{
            $payrollEmployeeAccountService->deleteEmployeeAccount($id);
            return redirect()->route('payroll.employee.maintenance.accounts.view', ['id' => $request->employee_number]);
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }


}
