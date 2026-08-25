<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\Designation;
use Illuminate\Support\Facades\Auth;

class DesignationObserver
{
    /**
     * Handle the Designation "created" event.
     */
    public function created(Designation $designation): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($designation),
            'model_id' => $designation->id,
            'new_values' => $designation->getAttributes(),
        ]);
    }

    /**
     * Handle the Designation "updated" event.
     */
    public function updated(Designation $designation): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($designation),
            'model_id' => $designation->id,
            'old_values' => $designation->getOriginal(),
            'new_values' => $designation->getAttributes(),
        ]);
    }

    /**
     * Handle the Designation "deleted" event.
     */
    public function deleted(Designation $designation): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($designation),
            'model_id' => $designation->id,
            'old_values' => $designation->getOriginal(),
        ]);
    }

    /**
     * Handle the Designation "restored" event.
     */
    public function restored(Designation $designation): void
    {
        //
    }

    /**
     * Handle the Designation "force deleted" event.
     */
    public function forceDeleted(Designation $designation): void
    {
        //
    }
}
