<?php

namespace App\Observers;

use App\Models\AttendanceLogEventTypes;
use App\Models\AuditTrail;
use Illuminate\Support\Facades\Auth;

class AttendanceLogEventTypeObserver
{
    /**
     * Handle the AttendanceLogEventTypes "created" event.
     */
    public function created(AttendanceLogEventTypes $attendanceLogEventTypes): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($attendanceLogEventTypes),
            'model_id' => $attendanceLogEventTypes->id,
            'new_values' => $attendanceLogEventTypes->getAttributes(),
        ]);
    }

    /**
     * Handle the AttendanceLogEventTypes "updated" event.
     */
    public function updated(AttendanceLogEventTypes $attendanceLogEventTypes): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($attendanceLogEventTypes),
            'model_id' => $attendanceLogEventTypes->id,
            'old_values' => $attendanceLogEventTypes->getOriginal(),
            'new_values' => $attendanceLogEventTypes->getAttributes(),
        ]);
    }

    /**
     * Handle the AttendanceLogEventTypes "deleted" event.
     */
    public function deleted(AttendanceLogEventTypes $attendanceLogEventTypes): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($attendanceLogEventTypes),
            'model_id' => $attendanceLogEventTypes->id,
            'old_values' => $attendanceLogEventTypes->getOriginal(),
        ]);
    }

    /**
     * Handle the AttendanceLogEventTypes "restored" event.
     */
    public function restored(AttendanceLogEventTypes $attendanceLogEventTypes): void
    {
        //
    }

    /**
     * Handle the AttendanceLogEventTypes "force deleted" event.
     */
    public function forceDeleted(AttendanceLogEventTypes $attendanceLogEventTypes): void
    {
        //
    }
}
