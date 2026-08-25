<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\PersonalInformation;
use Illuminate\Support\Facades\Auth;

class PersonalInformationObserver
{
    /**
     * Handle the PersonalInformation "created" event.
     */
    public function created(PersonalInformation $personalInformation): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($personalInformation),
            'model_id' => $personalInformation->id,
            'new_values' => $personalInformation->getAttributes(),
        ]);
    }

    /**
     * Handle the PersonalInformation "updated" event.
     */
    public function updated(PersonalInformation $personalInformation): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($personalInformation),
            'model_id' => $personalInformation->id,
            'old_values' => $personalInformation->getOriginal(),
            'new_values' => $personalInformation->getAttributes(),
        ]);
    }

    /**
     * Handle the PersonalInformation "deleted" event.
     */
    public function deleted(PersonalInformation $personalInformation): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($personalInformation),
            'model_id' => $personalInformation->id,
            'old_values' => $personalInformation->getOriginal(),
        ]);
    }

    /**
     * Handle the PersonalInformation "restored" event.
     */
    public function restored(PersonalInformation $personalInformation): void
    {
        //
    }

    /**
     * Handle the PersonalInformation "force deleted" event.
     */
    public function forceDeleted(PersonalInformation $personalInformation): void
    {
        //
    }
}
