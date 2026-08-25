<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\EmployeeLeaveCreditsHistory;
use Illuminate\Support\Facades\Auth;

class EmployeeLeaveCreditsHistoryObserver
{
    /**
     * Handle the EmployeeLeaveCreditHistory "created" event.
     */
    public function created(EmployeeLeaveCreditsHistory $employeeLeaveCreditHistory): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($employeeLeaveCreditHistory),
            'model_id' => $employeeLeaveCreditHistory->id,
            'old_values' => $employeeLeaveCreditHistory->getAttributes(),
        ]);
    }

    /**
     * Handle the EmployeeLeaveCreditHistory "updated" event.
     */
    public function updated(EmployeeLeaveCreditsHistory $employeeLeaveCreditHistory): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($employeeLeaveCreditHistory),
            'model_id' => $employeeLeaveCreditHistory->id,
            'old_values' => $employeeLeaveCreditHistory->getOriginal(),
            'new_values' => $employeeLeaveCreditHistory->getChanges(),
        ]);
    }

    /**
     * Handle the EmployeeLeaveCreditHistory "deleted" event.
     */
    public function deleted(EmployeeLeaveCreditsHistory $employeeLeaveCreditHistory): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($employeeLeaveCreditHistory),
            'model_id' => $employeeLeaveCreditHistory->id,
            'old_values' => $employeeLeaveCreditHistory->getOriginal(),
        ]);
    }

    /**
     * Handle the EmployeeLeaveCreditHistory "restored" event.
     */
    public function restored(EmployeeLeaveCreditsHistory $employeeLeaveCreditHistory): void
    {
        //
    }

    /**
     * Handle the EmployeeLeaveCreditHistory "force deleted" event.
     */
    public function forceDeleted(EmployeeLeaveCreditsHistory $employeeLeaveCreditHistory): void
    {
        //
    }
}
