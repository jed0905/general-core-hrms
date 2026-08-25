<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\LeaveStatus;
use Illuminate\Support\Facades\Auth;

class LeaveStatusObserver
{
    /**
     * Handle the LeaveStatus "created" event.
     */
    public function created(LeaveStatus $leaveStatus): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($leaveStatus),
            'model_id' => $leaveStatus->id,
            'new_values' => $leaveStatus->getAttributes(),
        ]);
    }

    /**
     * Handle the LeaveStatus "updated" event.
     */
    public function updated(LeaveStatus $leaveStatus): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($leaveStatus),
            'model_id' => $leaveStatus->id,
            'old_values' => $leaveStatus->getOriginal(),
            'new_values' => $leaveStatus->getAttributes(),
        ]);
    }

    /**
     * Handle the LeaveStatus "deleted" event.
     */
    public function deleted(LeaveStatus $leaveStatus): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($leaveStatus),
            'model_id' => $leaveStatus->id,
            'old_values' => $leaveStatus->getOriginal(),
        ]);
    }

    /**
     * Handle the LeaveStatus "restored" event.
     */
    public function restored(LeaveStatus $leaveStatus): void
    {
        //
    }

    /**
     * Handle the LeaveStatus "force deleted" event.
     */
    public function forceDeleted(LeaveStatus $leaveStatus): void
    {
        //
    }
}
