<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayrollDeductionRequest;
use App\Http\Requests\UpdatePayrollDeductionRequest;
use App\Services\PayrollDeductionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollDeductionController extends Controller
{
    public function index(PayrollDeductionService $payrollDeductionService)
    {
        $payrollDeductions = $payrollDeductionService->getPayrollDeductions();
        return Inertia::render('app/Payroll/Maintenance/Deduction/Index',[
            'payrollDeductions' => $payrollDeductions,
        ]);
    }

    public function create(PayrollDeductionService $payrollDeductionService){
        $operatingUnits = $payrollDeductionService->getOperatingUnits();
        return Inertia::render('app/Payroll/Maintenance/Deduction/Create',[
            'operatingUnits' => $operatingUnits,
        ]);
    }

   

    public function store(StorePayrollDeductionRequest $request, PayrollDeductionService $payrollDeductionService)
    {
        try{
            $payrollDeduction = $payrollDeductionService->store($request->validated());
            return redirect()->route('payroll.maintenance.deduction.index');
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function edit(PayrollDeductionService $payrollDeductionService, $id)
    {
        $payrollDeduction = $payrollDeductionService->getPayrollDeductionById($id);
        $operatingUnits = $payrollDeductionService->getOperatingUnits();
        return Inertia::render('app/Payroll/Maintenance/Deduction/Edit',[
            'payrollDeduction' => $payrollDeduction,
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function update(UpdatePayrollDeductionRequest $request, PayrollDeductionService $payrollDeductionService, $id)
    {
        try{
            $payrollDeduction = $payrollDeductionService->update($id, $request->validated());
            return redirect()->route('payroll.maintenance.deduction.index');
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function delete(PayrollDeductionService $payrollDeductionService, $id)
    {
        try{
            $payrollDeduction = $payrollDeductionService->delete($id);
            return redirect()->route('payroll.maintenance.deduction.index');
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
