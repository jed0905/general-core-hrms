<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\OtherInfoNonAcademicDistinction;
use Illuminate\Support\Facades\Auth;

class OtherInfoNonAcademicDistinctionObserver
{
    /**
     * Handle the OtherInfoNonAcademicDistinction "created" event.
     */
    public function created(OtherInfoNonAcademicDistinction $otherInfoNonAcademicDistinction): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($otherInfoNonAcademicDistinction),
            'model_id' => $otherInfoNonAcademicDistinction->id,
            'new_values' => $otherInfoNonAcademicDistinction->getAttributes(),
        ]);
    }

    /**
     * Handle the OtherInfoNonAcademicDistinction "updated" event.
     */
    public function updated(OtherInfoNonAcademicDistinction $otherInfoNonAcademicDistinction): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($otherInfoNonAcademicDistinction),
            'model_id' => $otherInfoNonAcademicDistinction->id,
            'old_values' => $otherInfoNonAcademicDistinction->getOriginal(),
            'new_values' => $otherInfoNonAcademicDistinction->getAttributes(),
        ]);
    }

    /**
     * Handle the OtherInfoNonAcademicDistinction "deleted" event.
     */
    public function deleted(OtherInfoNonAcademicDistinction $otherInfoNonAcademicDistinction): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($otherInfoNonAcademicDistinction),
            'model_id' => $otherInfoNonAcademicDistinction->id,
            'old_values' => $otherInfoNonAcademicDistinction->getOriginal(),
        ]);
    }

    /**
     * Handle the OtherInfoNonAcademicDistinction "restored" event.
     */
    public function restored(OtherInfoNonAcademicDistinction $otherInfoNonAcademicDistinction): void
    {
        //
    }

    /**
     * Handle the OtherInfoNonAcademicDistinction "force deleted" event.
     */
    public function forceDeleted(OtherInfoNonAcademicDistinction $otherInfoNonAcademicDistinction): void
    {
        //
    }
}
