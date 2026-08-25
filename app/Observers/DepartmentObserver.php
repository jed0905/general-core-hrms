<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\Department;
use Illuminate\Support\Facades\Auth;

class DepartmentObserver
{
    /**
     * Handle the Department "created" event.
     */
    public function created(Department $department): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($department),
            'model_id' => $department->id,
            'new_values' => $department->getAttributes(),
        ]);
    }

    /**
     * Handle the Department "updated" event.
     */
    public function updated(Department $department): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($department),
            'model_id' => $department->id,
            'old_values' => $department->getOriginal(),
            'new_values' => $department->getAttributes(),
        ]);
    }

    /**
     * Handle the Department "deleted" event.
     */
    public function deleted(Department $department): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($department),
            'model_id' => $department->id,
            'old_values' => $department->getOriginal(),
        ]);
    }

    /**
     * Handle the Department "restored" event.
     */
    public function restored(Department $department): void
    {
        //
    }

    /**
     * Handle the Department "force deleted" event.
     */
    public function forceDeleted(Department $department): void
    {
        //
    }
}
