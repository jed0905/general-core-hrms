<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\EmployeeAttendanceLogEvents;

class EmployeeAttendanceLogEventObserver
{
    /**
     * Handle the EmployeeAttendanceLogEvents "created" event.
     */
    public function created(EmployeeAttendanceLogEvents $employeeAttendanceLogEvents): void
    {
        AuditTrail::create([
            'user_id' => auth()->id(),
            'action' => 'created',
            'model_type' => get_class($employeeAttendanceLogEvents),
            'model_id' => $employeeAttendanceLogEvents->id,
            'new_values' => $employeeAttendanceLogEvents->getAttributes(),
        ]);
    }

    /**
     * Handle the EmployeeAttendanceLogEvents "updated" event.
     */
    public function updated(EmployeeAttendanceLogEvents $employeeAttendanceLogEvents): void
    {
        AuditTrail::create([
            'user_id' => auth()->id(),
            'action' => 'updated',
            'model_type' => get_class($employeeAttendanceLogEvents),
            'model_id' => $employeeAttendanceLogEvents->id,
            'old_values' => $employeeAttendanceLogEvents->getOriginal(),
            'new_values' => $employeeAttendanceLogEvents->getAttributes(),
        ]);
    }

    /**
     * Handle the EmployeeAttendanceLogEvents "deleted" event.
     */
    public function deleted(EmployeeAttendanceLogEvents $employeeAttendanceLogEvents): void
    {
        AuditTrail::create([
            'user_id' => auth()->id(),
            'action' => 'deleted',
            'model_type' => get_class($employeeAttendanceLogEvents),
            'model_id' => $employeeAttendanceLogEvents->id,
            'old_values' => $employeeAttendanceLogEvents->getOriginal(),
        ]);
    }

    /**
     * Handle the EmployeeAttendanceLogEvents "restored" event.
     */
    public function restored(EmployeeAttendanceLogEvents $employeeAttendanceLogEvents): void
    {
        //
    }

    /**
     * Handle the EmployeeAttendanceLogEvents "force deleted" event.
     */
    public function forceDeleted(EmployeeAttendanceLogEvents $employeeAttendanceLogEvents): void
    {
        //
    }
}
