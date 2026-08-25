<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\LeaveApplicationDate;
use Illuminate\Support\Facades\Auth;

class LeaveApplicationDatesObserver
{
    /**
     * Handle the LeaveApplicationDate "created" event.
     */
    public function created(LeaveApplicationDate $leaveApplicationDate): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($leaveApplicationDate),
            'model_id' => $leaveApplicationDate->id,
            'new_values' => $leaveApplicationDate->getAttributes(),
        ]);
    }

    /**
     * Handle the LeaveApplicationDate "updated" event.
     */
    public function updated(LeaveApplicationDate $leaveApplicationDate): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($leaveApplicationDate),
            'model_id' => $leaveApplicationDate->id,
            'old_values' => $leaveApplicationDate->getOriginal(),
            'new_values' => $leaveApplicationDate->getAttributes(),
        ]);
    }

    /**
     * Handle the LeaveApplicationDate "deleted" event.
     */
    public function deleted(LeaveApplicationDate $leaveApplicationDate): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($leaveApplicationDate),
            'model_id' => $leaveApplicationDate->id,
            'old_values' => $leaveApplicationDate->getOriginal(),
        ]);
    }

    /**
     * Handle the LeaveApplicationDate "restored" event.
     */
    public function restored(LeaveApplicationDate $leaveApplicationDate): void
    {
        //
    }

    /**
     * Handle the LeaveApplicationDate "force deleted" event.
     */
    public function forceDeleted(LeaveApplicationDate $leaveApplicationDate): void
    {
        //
    }
}
