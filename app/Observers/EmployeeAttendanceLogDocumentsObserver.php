<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\EmployeeAttendanceLogDocuments;
use Illuminate\Support\Facades\Auth;

class EmployeeAttendanceLogDocumentsObserver
{
    /**
     * Handle the EmployeeAttendanceLogDocuments "created" event.
     */
    public function created(EmployeeAttendanceLogDocuments $employeeAttendanceLogDocuments): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($employeeAttendanceLogDocuments),
            'model_id' => $employeeAttendanceLogDocuments->id,
            'new_values' => $employeeAttendanceLogDocuments->getAttributes(),
        ]);
    }

    /**
     * Handle the EmployeeAttendanceLogDocuments "updated" event.
     */
    public function updated(EmployeeAttendanceLogDocuments $employeeAttendanceLogDocuments): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($employeeAttendanceLogDocuments),
            'model_id' => $employeeAttendanceLogDocuments->id,
            'old_values' => $employeeAttendanceLogDocuments->getOriginal(),
            'new_values' => $employeeAttendanceLogDocuments->getAttributes(),
        ]);
    }

    /**
     * Handle the EmployeeAttendanceLogDocuments "deleted" event.
     */
    public function deleted(EmployeeAttendanceLogDocuments $employeeAttendanceLogDocuments): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($employeeAttendanceLogDocuments),
            'model_id' => $employeeAttendanceLogDocuments->id,
            'old_values' => $employeeAttendanceLogDocuments->getOriginal(),
        ]);
    }

    /**
     * Handle the EmployeeAttendanceLogDocuments "restored" event.
     */
    public function restored(EmployeeAttendanceLogDocuments $employeeAttendanceLogDocuments): void
    {
        //
    }

    /**
     * Handle the EmployeeAttendanceLogDocuments "force deleted" event.
     */
    public function forceDeleted(EmployeeAttendanceLogDocuments $employeeAttendanceLogDocuments): void
    {
        //
    }
}
