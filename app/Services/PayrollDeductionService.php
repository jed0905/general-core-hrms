<?php
 namespace App\Services;

use App\Http\Resources\PayrollDeductionResource;
use App\Models\OperatingUnit;
use App\Models\PayrollDeduction;
use Illuminate\Support\Facades\URL;

 class PayrollDeductionService
 {

  public function getPayrollDeductions(){
    $payrollDeductions = fn() => PayrollDeductionResource::collection(PayrollDeduction::with('operatingUnit')->paginate(10)->through(function($payrollDeduction){
      $payrollDeduction->signed_url = URL::signedRoute('payroll.maintenance.deduction.edit', ['id' => $payrollDeduction->id]);
      return $payrollDeduction;
    }));
    return $payrollDeductions;
  }

  public function getPayrollDeductionById($id)
  {
    $payrollDeduction = PayrollDeduction::findOrFail($id);
    return $payrollDeduction;
  }

  public function getOperatingUnits(){
    $operatingUnits = OperatingUnit::get();
    return $operatingUnits;
  }

  public function store(array $data)
  {
    $payrollDeduction = PayrollDeduction::create($data);
    return $payrollDeduction;
  }

  public function update(int $id, array $data)
  {
    $payrollDeduction = PayrollDeduction::findOrFail($id);
    $payrollDeduction->update($data);
    return $payrollDeduction;
  }

  public function delete($id)
  {
    $payrollDeduction = PayrollDeduction::findOrFail($id);
    $payrollDeduction->delete();
    return $payrollDeduction;
  }
 }