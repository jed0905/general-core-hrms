<?php

 namespace App\Services;

use App\Http\Resources\PayrollProjectFundResource;
use App\Models\Employee;
use App\Models\JobStatus;
use App\Models\OperatingUnit;
use App\Models\PayrollEmployeeProjectFund;
use App\Models\PayrollProjectFund;
use Illuminate\Support\Facades\URL;

 class PayrollProjectFundService
 {
    public function getPayrollProjectFunds()
    {
        $payrollProjectFunds = fn() => PayrollProjectFundResource::collection(PayrollProjectFund::with('jobStatus', 'operatingUnit')->paginate(10)
        ->through(function($payrollProjectFund){
            $payrollProjectFund->signed_url = URL::signedRoute('payroll.maintenance.projectfund.edit', ['id' => $payrollProjectFund->id]);
            $payrollProjectFund->assign_link = URL::signedRoute('payroll.maintenance.projectfund.assign', ['id' => $payrollProjectFund->id]);
            return $payrollProjectFund;
        }));
        return $payrollProjectFunds;
    }

    public function getOperatingUnits(){
        $operatingUnits = OperatingUnit::get();
        return $operatingUnits;
    }

    public function getPayrollProjectFundById($id)
    {
        $payrollProjectFund = PayrollProjectFund::with('jobStatus')->findOrFail($id);
        return $payrollProjectFund;
    }

    public function getJobStatus(){
        $jobStatus = JobStatus::get();
        return $jobStatus;
    }

    public function store(array $data)
    {
        $payrollProjectFund = PayrollProjectFund::where('name', $data['name'])->where('employee_status', $data['employee_status'])->first();
        if($payrollProjectFund){
            throw new \Exception('The payroll project for the selected employee status already exists.');
        }

        $payrollProjectFund = PayrollProjectFund::where('name', $data['name'])->where('employee_type', $data['employee_type'])->first();
        if($payrollProjectFund){
            throw new \Exception('The payroll project for the selected employee type already exists.');
        }

        $payrollProjectFund = PayrollProjectFund::create($data);
        return $payrollProjectFund;
    }

    public function update(array $data, $id)
    {
        // Check for duplicate name with same employee status (excluding current record)
        $existingByStatus = PayrollProjectFund::where('name', $data['name'])
            ->where('employee_status', $data['employee_status'])
            ->where('id', '!=', $id)
            ->first();
        if($existingByStatus){
            throw new \Exception('The payroll project for the selected employee status already exists.');
        }

        // Check for duplicate name with same employee type (excluding current record)
        $existingByType = PayrollProjectFund::where('name', $data['name'])
            ->where('employee_type', $data['employee_type'])
            ->where('id', '!=', $id)
            ->first();
        if($existingByType){
            throw new \Exception('The payroll project for the selected employee type already exists.');
        }

        $payrollProjectFund = PayrollProjectFund::where('name', $data['name'])
            ->where('employee_status', $data['employee_status'])
            ->where('employee_type', $data['employee_type'])
            ->where('operating_unit_id', $data['operating_unit_id'])
            ->where('id', '!=', $id)
            ->first();
        if($payrollProjectFund){
            throw new \Exception('The payroll project for the selected employee status and employee type already exists in this operating unit.');
        }

        $payrollProjectFund = PayrollProjectFund::findOrFail($id);
        $payrollProjectFund->update([
            'name' => $data['name'],
            'allocation' => $data['allocation'],
            'employee_status' => $data['employee_status'],
            'employee_type' => $data['employee_type'],
        ]);
        return $payrollProjectFund;
    }

    public function delete($id)
    {
        /* On delete of fund, we need to delete employess that are assigned to the fund */
        $assignedEmployees = PayrollEmployeeProjectFund::where('project_fund_id', $id)->get();
        foreach($assignedEmployees as $assignedEmployee){
            $assignedEmployee->delete();
        }


        $payrollProjectFund = PayrollProjectFund::findOrFail($id);
        $payrollProjectFund->delete();
        return $payrollProjectFund;
    }

    public function getEmployeesThatDoesntHaveProjectFund($id){

        $projectFund = PayrollProjectFund::findOrFail($id);
        
        // Get employees who don't have ANY project fund assignment
        $employeesWithoutAnyFund = Employee::with(['personalInformation', 'position'])->whereDoesntHave('payrollProjectFunds')
            ->where('job_status_id', $projectFund->employee_status)
            ->where('employee_type', $projectFund->employee_type)
            ->where('operating_unit_id', auth()->user()->employee->operating_unit_id)
            ->get();
            
        // Get employees who are assigned to OTHER project funds (for reassignment)
        $employeesWithOtherFunds = Employee::with(['personalInformation', 'position'])->whereHas('payrollProjectFunds', function($query) use ($projectFund){
            $query->where('project_fund_id', '!=', $projectFund->id);
        })->where('job_status_id', $projectFund->employee_status)
        ->where('employee_type', $projectFund->employee_type)
        ->where('operating_unit_id', auth()->user()->employee->operating_unit_id)
        ->get();
        
        // Combine both collections
        $allEligibleEmployees = $employeesWithoutAnyFund->merge($employeesWithOtherFunds);
        
        return $allEligibleEmployees;
    }

    public function assignEmployeeToProjectFund($employeeNumber, $projectFundId){
        // First, remove any existing project fund assignment for this employee
        PayrollEmployeeProjectFund::where('employee_number', $employeeNumber)->delete();
        
        // Then assign to the new project fund
        PayrollEmployeeProjectFund::create([
            'employee_number' => $employeeNumber,
            'project_fund_id' => $projectFundId,
            'status' => 'active'
        ]);
        
        return true;
    }

    public function removeEmployeeFromProjectFund($employeeNumber){
        PayrollEmployeeProjectFund::where('employee_number', $employeeNumber)->delete();
        return true;
    }

    public function getAssignedEmployees($projectFundId){
        return Employee::with(['personalInformation', 'position'])->whereHas('payrollProjectFunds', function($query) use ($projectFundId){
            $query->where('project_fund_id', $projectFundId);
        })->get();
    }

    public function assignMultipleEmployeesToProjectFund($employeeNumbers, $projectFundId){
        try {
            \DB::transaction(function() use ($employeeNumbers, $projectFundId) {
                foreach ($employeeNumbers as $employeeNumber) {
                    // Remove any existing project fund assignment for this employee
                    PayrollEmployeeProjectFund::where('employee_number', $employeeNumber)->delete();
                    
                    // Assign to the new project fund
                    PayrollEmployeeProjectFund::create([
                        'employee_number' => $employeeNumber,
                        'project_fund_id' => $projectFundId,
                        'status' => 'active'
                    ]);
                }
            });
            
            $count = count($employeeNumbers);
            return [
                'success' => true,
                'message' => "Successfully assigned {$count} employee(s) to the project fund",
                'assigned_count' => $count
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to assign employees: ' . $e->getMessage());
        }
    }

    public function removeMultipleEmployeesFromProjectFund($employeeNumbers){
        try {
            \Log::info('Service: Removing employees from project fund', [
                'employee_numbers' => $employeeNumbers,
                'count' => count($employeeNumbers)
            ]);
            
            \DB::transaction(function() use ($employeeNumbers) {
                $deletedCount = PayrollEmployeeProjectFund::whereIn('employee_number', $employeeNumbers)->delete();
                \Log::info('Service: Deleted records', ['deleted_count' => $deletedCount]);
            });
            
            $count = count($employeeNumbers);
            return [
                'success' => true,
                'message' => "Successfully removed {$count} employee(s) from project funds",
                'removed_count' => $count
            ];
        } catch (\Exception $e) {
            \Log::error('Service: Failed to remove employees', [
                'error' => $e->getMessage(),
                'employee_numbers' => $employeeNumbers
            ]);
            throw new \Exception('Failed to remove employees: ' . $e->getMessage());
        }
    }

}