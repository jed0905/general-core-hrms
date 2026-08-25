<?php

namespace App\Services;

use App\Http\Resources\PayrollAccountTypeResource;
use App\Models\OperatingUnit;
use App\Models\PayrollAccountType;
use Illuminate\Support\Facades\URL;

class PayrollAccountTypeService
{
    public function getPayrollAccountTypes()
    { 
      $payrollAccountTypes = fn() => PayrollAccountTypeResource::collection(PayrollAccountType::with('operatingUnit')->paginate(10)->through(function($payrollAccountType) {
          $payrollAccountType->signed_url = URL::signedRoute('payroll.maintenance.accounttype.edit', ['id' => $payrollAccountType->id]);
          return $payrollAccountType;
      }));
      return $payrollAccountTypes;
    }

    public function getOperatingUnits()
    {
      $operatingUnits = OperatingUnit::get();
      return $operatingUnits;
    }

    
    public function storePayrollAccountType(array $data)
    {
      $payrollAccountType = PayrollAccountType::create($data);
      return $payrollAccountType;
    }

    public function getSpecificPayrollAccountType(int $id)
    {
      $payrollAccountType = PayrollAccountType::findOrFail($id);
      return $payrollAccountType;
    }


    public function updatePayrollAccountType(int $id, array $data)
    {
      $payrollAccountType = PayrollAccountType::findOrFail($id);
      $payrollAccountType->update($data);
      return $payrollAccountType;
    }

    public function deletePayrollAccountType(int $id)
    {
      $payrollAccountType = PayrollAccountType::findOrFail($id);
      $payrollAccountType->delete();
      return $payrollAccountType;
    }
}
