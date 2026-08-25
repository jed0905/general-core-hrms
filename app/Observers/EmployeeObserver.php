<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;

class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($employee),
            'model_id' => $employee->id,
            'new_values' => $employee->getAttributes(),
        ]);
    }

    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($employee),
            'model_id' => $employee->id,
            'old_values' => $employee->getOriginal(),
            'new_values' => $employee->getAttributes(),
        ]);
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($employee),
            'model_id' => $employee->id,
            'old_values' => $employee->getOriginal(),
        ]);
    }

    /**
     * Handle the Employee "restored" event.
     */
    public function restored(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "force deleted" event.
     */
    public function forceDeleted(Employee $employee): void
    {
        //
    }
}
