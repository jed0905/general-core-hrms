<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayrollProjectFundRequest;
use App\Http\Requests\UpdatePayrollProjectFundRequest;
use App\Services\PayrollProjectFundService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollProjectFundController extends Controller
{
    public function index(PayrollProjectFundService $payrollProjectFundService)
    {
        $payrollProjectFunds = $payrollProjectFundService->getPayrollProjectFunds();
        $operatingUnits = $payrollProjectFundService->getOperatingUnits();
        return Inertia::render('app/Payroll/Maintenance/ProjectFund/Index',[
            'payrollProjectFunds' => $payrollProjectFunds,
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function create(PayrollProjectFundService $payrollProjectFundService){
        $jobStatus = $payrollProjectFundService->getJobStatus();
        $operatingUnits = $payrollProjectFundService->getOperatingUnits();
        return Inertia::render('app/Payroll/Maintenance/ProjectFund/Create',[
            'jobStatus' => $jobStatus,
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function store(StorePayrollProjectFundRequest $request, PayrollProjectFundService $payrollProjectFundService){
        try{
            $payrollProjectFundService->store($request->validated());
            return redirect()->route('payroll.maintenance.projectfund.index');
        }catch(\Exception $e){
            return redirect()->route('payroll.maintenance.projectfund.index')->with('error', $e->getMessage());
        }
    }

    public function edit(PayrollProjectFundService $payrollProjectFundService, $id){
        
        $payrollProjectFund = $payrollProjectFundService->getPayrollProjectFundById($id);
        $jobStatus = $payrollProjectFundService->getJobStatus();
        return Inertia::render('app/Payroll/Maintenance/ProjectFund/Edit',[
            'payrollProjectFund' => $payrollProjectFund,
            'jobStatus' => $jobStatus,
        ]);
       
    }

    public function update(UpdatePayrollProjectFundRequest $request, PayrollProjectFundService $payrollProjectFundService, $id){
       
        try{
            $payrollProjectFundService->update($request->validated(), $id);
            return redirect()->route('payroll.maintenance.projectfund.index');
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }

    }

    public function delete(PayrollProjectFundService $payrollProjectFundService, $id){
        try{
            $payrollProjectFundService->delete($id);
            return redirect()->route('payroll.maintenance.projectfund.index');
        }catch(\Exception $e){
            return redirect()->route('payroll.maintenance.projectfund.index')->with('error', $e->getMessage());
        }
    }

    public function assign(PayrollProjectFundService $payrollProjectFundService, $id){
 
        
        $payrollProjectFund = $payrollProjectFundService->getPayrollProjectFundById($id);
        $employees = $payrollProjectFundService->getEmployeesThatDoesntHaveProjectFund($id);
        $assignedEmployees = $payrollProjectFundService->getAssignedEmployees($id);
        return Inertia::render('app/Payroll/Maintenance/ProjectFund/Assign',[
            'payrollProjectFund' => $payrollProjectFund,
            'employees' => $employees,
            'assignedEmployees' => $assignedEmployees,
        ]);
    }

    public function assignEmployee(Request $request, PayrollProjectFundService $payrollProjectFundService){
        try{
            $payrollProjectFundService->assignEmployeeToProjectFund($request->employee_number, $request->project_fund_id);
            return redirect()->back()->with('success', 'Employee assigned successfully');
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function removeEmployee(PayrollProjectFundService $payrollProjectFundService, $employeeNumber){
        try{
            $payrollProjectFundService->removeEmployeeFromProjectFund($employeeNumber);
            return redirect()->back()->with('success', 'Employee removed successfully');
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function assignEmployeesBulk(Request $request, PayrollProjectFundService $payrollProjectFundService){
        try{
            $employeeNumbers = $request->input('employee_numbers');
            $projectFundId = $request->input('project_fund_id');
            
            if (empty($employeeNumbers) || !is_array($employeeNumbers)) {
                return redirect()->back()->with('error', 'No employees selected for assignment');
            }
            
            $result = $payrollProjectFundService->assignMultipleEmployeesToProjectFund($employeeNumbers, $projectFundId);
            return redirect()->back()->with('success', $result['message']);
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function removeEmployeesBulk(Request $request, PayrollProjectFundService $payrollProjectFundService){
        try{
            $employeeNumbers = $request->input('employee_numbers');
            
            // Debug: Log what we're receiving
            \Log::info('Bulk Remove Request Data:', [
                'employee_numbers' => $employeeNumbers,
                'all_input' => $request->all()
            ]);
            
            if (empty($employeeNumbers) || !is_array($employeeNumbers)) {
                return redirect()->back()->with('error', 'No employees selected for removal. Received: ' . json_encode($employeeNumbers));
            }
            
            $result = $payrollProjectFundService->removeMultipleEmployeesFromProjectFund($employeeNumbers);
            return redirect()->back()->with('success', $result['message']);
        }catch(\Exception $e){
            \Log::error('Bulk Remove Error: ' . $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
