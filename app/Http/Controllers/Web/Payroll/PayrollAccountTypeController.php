<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayrollAccountTypeRequest;
use App\Http\Requests\UpdatePayrollAccountTypeRequest;
use App\Services\PayrollAccountTypeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollAccountTypeController extends Controller
{
    public function index(PayrollAccountTypeService $payrollAccountTypeService){
        $payrollAccountTypes = $payrollAccountTypeService->getPayrollAccountTypes();
        return Inertia::render('app/Payroll/Maintenance/AccountType/Index', [
            'payrollAccountTypes' => $payrollAccountTypes
        ]);
    }

    public function create(PayrollAccountTypeService $payrollAccountTypeService){
        $operatingUnits = $payrollAccountTypeService->getOperatingUnits();
        return Inertia::render('app/Payroll/Maintenance/AccountType/Create',[
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function store(StorePayrollAccountTypeRequest $request, PayrollAccountTypeService $payrollAccountTypeService){
        try{
            $payrollAccountTypeService->storePayrollAccountType($request->validated());
            return redirect()->route('payroll.maintenance.accounttype.index');
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function edit(String $id, PayrollAccountTypeService $payrollAccountTypeService){
        $payrollAccountType = $payrollAccountTypeService->getSpecificPayrollAccountType($id);
        $operatingUnits = $payrollAccountTypeService->getOperatingUnits();
        return Inertia::render('app/Payroll/Maintenance/AccountType/Edit', [
            'payrollAccountType' => $payrollAccountType,
            'operatingUnits' => $operatingUnits
        ]);
    }

    public function update(String $id, UpdatePayrollAccountTypeRequest $request, PayrollAccountTypeService $payrollAccountTypeService){
        try{
            $payrollAccountTypeService->updatePayrollAccountType($id, $request->validated());
            return redirect()->route('payroll.maintenance.accounttype.index');
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function delete(String $id, PayrollAccountTypeService $payrollAccountTypeService){
        try{
            $payrollAccountTypeService->deletePayrollAccountType($id);
            return redirect()->route('payroll.maintenance.accounttype.index');
        }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
