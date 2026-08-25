<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\FamilyBackground;
use Illuminate\Support\Facades\Auth;

class FamilyBackgroundObserver
{
    /**
     * Handle the FamilyBackground "created" event.
     */
    public function created(FamilyBackground $familyBackground): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($familyBackground),
            'model_id' => $familyBackground->id,
            'new_values' => $familyBackground->getAttributes(),
        ]);
    }

    /**
     * Handle the FamilyBackground "updated" event.
     */
    public function updated(FamilyBackground $familyBackground): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($familyBackground),
            'model_id' => $familyBackground->id,
            'old_values' => $familyBackground->getOriginal(),
            'new_values' => $familyBackground->getAttributes(),
        ]);
    }

    /**
     * Handle the FamilyBackground "deleted" event.
     */
    public function deleted(FamilyBackground $familyBackground): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($familyBackground),
            'model_id' => $familyBackground->id,
            'old_values' => $familyBackground->getOriginal(),
        ]);
    }

    /**
     * Handle the FamilyBackground "restored" event.
     */
    public function restored(FamilyBackground $familyBackground): void
    {
        //
    }

    /**
     * Handle the FamilyBackground "force deleted" event.
     */
    public function forceDeleted(FamilyBackground $familyBackground): void
    {
        //
    }
}
