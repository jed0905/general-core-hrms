<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\EmployeeAdditionalInformation;
use Illuminate\Support\Facades\Auth;

class EmployeeAdditionalInformationObserver
{
    /**
     * Handle the EmployeeAdditionalInformation "created" event.
     */
    public function created(EmployeeAdditionalInformation $employeeAdditionalInformation): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($employeeAdditionalInformation),
            'model_id' => $employeeAdditionalInformation->id,
            'new_values' => $employeeAdditionalInformation->getAttributes(),
        ]);
    }

    /**
     * Handle the EmployeeAdditionalInformation "updated" event.
     */
    public function updated(EmployeeAdditionalInformation $employeeAdditionalInformation): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($employeeAdditionalInformation),
            'model_id' => $employeeAdditionalInformation->id,
            'old_values' => $employeeAdditionalInformation->getOriginal(),
            'new_values' => $employeeAdditionalInformation->getAttributes(),
        ]);
    }

    /**
     * Handle the EmployeeAdditionalInformation "deleted" event.
     */
    public function deleted(EmployeeAdditionalInformation $employeeAdditionalInformation): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($employeeAdditionalInformation),
            'model_id' => $employeeAdditionalInformation->id,
            'old_values' => $employeeAdditionalInformation->getOriginal(),
        ]);
    }

    /**
     * Handle the EmployeeAdditionalInformation "restored" event.
     */
    public function restored(EmployeeAdditionalInformation $employeeAdditionalInformation): void
    {
        //
    }

    /**
     * Handle the EmployeeAdditionalInformation "force deleted" event.
     */
    public function forceDeleted(EmployeeAdditionalInformation $employeeAdditionalInformation): void
    {
        //
    }
}
