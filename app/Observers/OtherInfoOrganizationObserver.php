<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\OtherInfoOrganization;
use Illuminate\Support\Facades\Auth;

class OtherInfoOrganizationObserver
{
    /**
     * Handle the OtherInfoOrganization "created" event.
     */
    public function created(OtherInfoOrganization $otherInfoOrganization): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($otherInfoOrganization),
            'model_id' => $otherInfoOrganization->id,
            'new_values' => $otherInfoOrganization->getAttributes(),
        ]);
    }

    /**
     * Handle the OtherInfoOrganization "updated" event.
     */
    public function updated(OtherInfoOrganization $otherInfoOrganization): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($otherInfoOrganization),
            'model_id' => $otherInfoOrganization->id,
            'old_values' => $otherInfoOrganization->getOriginal(),
            'new_values' => $otherInfoOrganization->getAttributes(),
        ]);
    }

    /**
     * Handle the OtherInfoOrganization "deleted" event.
     */
    public function deleted(OtherInfoOrganization $otherInfoOrganization): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($otherInfoOrganization),
            'model_id' => $otherInfoOrganization->id,
            'old_values' => $otherInfoOrganization->getOriginal(),
        ]);
    }

    /**
     * Handle the OtherInfoOrganization "restored" event.
     */
    public function restored(OtherInfoOrganization $otherInfoOrganization): void
    {
        //
    }

    /**
     * Handle the OtherInfoOrganization "force deleted" event.
     */
    public function forceDeleted(OtherInfoOrganization $otherInfoOrganization): void
    {
        //
    }
}
