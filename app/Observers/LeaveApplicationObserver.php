<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\LeaveApplication;
use Illuminate\Support\Facades\Auth;

class LeaveApplicationObserver
{
    /**
     * Handle the LeaveApplication "created" event.
     */
    public function created(LeaveApplication $leaveApplication): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($leaveApplication),
            'model_id' => $leaveApplication->id,
            'old_values' => $leaveApplication->getAttributes(),
        ]);
    }

    /**
     * Handle the LeaveApplication "updated" event.
     */
    public function updated(LeaveApplication $leaveApplication): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($leaveApplication),
            'model_id' => $leaveApplication->id,
            'old_values' => $leaveApplication->getOriginal(),
            'new_values' => $leaveApplication->getAttributes(),
        ]);
    }

    /**
     * Handle the LeaveApplication "deleted" event.
     */
    public function deleted(LeaveApplication $leaveApplication): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($leaveApplication),
            'model_id' => $leaveApplication->id,
            'old_values' => $leaveApplication->getOriginal(),
        ]);
    }

    /**
     * Handle the LeaveApplication "restored" event.
     */
    public function restored(LeaveApplication $leaveApplication): void
    {
        //
    }

    /**
     * Handle the LeaveApplication "force deleted" event.
     */
    public function forceDeleted(LeaveApplication $leaveApplication): void
    {
        //
    }
}
