<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\CivilServiceEligibility;
use Illuminate\Support\Facades\Auth;

class CivilServiceEligibilityObserver
{
    /**
     * Handle the CivilServiceEligibility "created" event.
     */
    public function created(CivilServiceEligibility $civilServiceEligibility): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($civilServiceEligibility),
            'model_id' => $civilServiceEligibility->id,
            'new_values' => $civilServiceEligibility->getAttributes(),
        ]);
    }

    /**
     * Handle the CivilServiceEligibility "updated" event.
     */
    public function updated(CivilServiceEligibility $civilServiceEligibility): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($civilServiceEligibility),
            'model_id' => $civilServiceEligibility->id,
            'old_values' => $civilServiceEligibility->getOriginal(),
            'new_values' => $civilServiceEligibility->getAttributes(),
        ]);
    }

    /**
     * Handle the CivilServiceEligibility "deleted" event.
     */
    public function deleted(CivilServiceEligibility $civilServiceEligibility): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($civilServiceEligibility),
            'model_id' => $civilServiceEligibility->id,
            'old_values' => $civilServiceEligibility->getOriginal(),
        ]);
    }

    /**
     * Handle the CivilServiceEligibility "restored" event.
     */
    public function restored(CivilServiceEligibility $civilServiceEligibility): void
    {
        //
    }

    /**
     * Handle the CivilServiceEligibility "force deleted" event.
     */
    public function forceDeleted(CivilServiceEligibility $civilServiceEligibility): void
    {
        //
    }
}
