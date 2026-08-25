<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayrollEmployeeDeductionRequest;
use App\Http\Requests\UpdatePayrollEmployeeDeductionRequest;
use App\Services\PayrollEmployeeDeductionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollEmployeeDeductionController extends Controller
{
    public function index(Request $request, PayrollEmployeeDeductionService $payrollEmployeeDeductionService)
    {
        $jobStatus = $payrollEmployeeDeductionService->getJobStatus();
        
        // $employees = [];
        // if ($request->isMethod('post') && $request->job_status && $request->employee_type) {
            $perPage = $request->get('per_page', 10);
        $employees = $request !== null ?
        $payrollEmployeeDeductionService->getEmployees($request, $perPage) : [];
        // }
        
        return Inertia::render('app/Payroll/EmployeeMaintenance/EmployeeDeductions/Index', [
            'jobStatus' => $jobStatus,
            'employees' => $employees
        ]);
    }

    public function view($id, PayrollEmployeeDeductionService $payrollEmployeeDeductionService)
    {
        
        $employee = $payrollEmployeeDeductionService->getEmployeeById($id);
        $employeeDeductions = $payrollEmployeeDeductionService->getEmployeeDeductions($id);
        $navigation = $payrollEmployeeDeductionService->getEmployeeNavigation($id);
        
        return Inertia::render('app/Payroll/EmployeeMaintenance/EmployeeDeductions/View', [
            'employee' => $employee,
            'employeeDeductions' => $employeeDeductions,
            'navigation' => $navigation
        ]);
    }

    public function create($id, PayrollEmployeeDeductionService $payrollEmployeeDeductionService)
    {
        $employee = $payrollEmployeeDeductionService->getEmployeeById($id);
        $payrollDeductions = $payrollEmployeeDeductionService->getPayrollDeductions();
        return Inertia::render('app/Payroll/EmployeeMaintenance/EmployeeDeductions/Create', [
            'employee' => $employee,
            'payrollDeductions' => $payrollDeductions
        ]);
    }

    public function store(StorePayrollEmployeeDeductionRequest $request, PayrollEmployeeDeductionService $payrollEmployeeDeductionService)
    {
        try{
            $payrollEmployeeDeduction = $payrollEmployeeDeductionService->store($request->validated());
            return redirect()->route('payroll.employee.maintenance.deductions.view', ['id' => $request->employee_number]);
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function edit($id, PayrollEmployeeDeductionService $payrollEmployeeDeductionService)
    {
        $payrollDeductions = $payrollEmployeeDeductionService->getPayrollDeductions();
        $payrollEmployeeDeduction = $payrollEmployeeDeductionService->getPayrollEmployeeDeductionById($id);
        return Inertia::render('app/Payroll/EmployeeMaintenance/EmployeeDeductions/Edit', [
            'payrollEmployeeDeduction' => $payrollEmployeeDeduction,
            'payrollDeductions' => $payrollDeductions,
        ]);
    }

    public function update(UpdatePayrollEmployeeDeductionRequest $request, PayrollEmployeeDeductionService $payrollEmployeeDeductionService, $id)
    {
        try{
            $payrollEmployeeDeduction = $payrollEmployeeDeductionService->update($id, $request->validated());
            return redirect()->route('payroll.employee.maintenance.deductions.view', ['id' => $request->employee_number]);
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function delete(PayrollEmployeeDeductionService $payrollEmployeeDeductionService, $id)
    {
        try{
            $payrollEmployeeDeduction = $payrollEmployeeDeductionService->delete($id);
            return redirect()->route('payroll.employee.maintenance.deductions.view', ['id' => $payrollEmployeeDeduction->employee_number]);
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
