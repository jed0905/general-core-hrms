<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\OperatingUnit;
use Illuminate\Support\Facades\Auth;

class OperatingUnitObserver
{
    /**
     * Handle the OperatingUnit "created" event.
     */
    public function created(OperatingUnit $operatingUnit): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($operatingUnit),
            'model_id' => $operatingUnit->id,
            'new_values' => $operatingUnit->getAttributes(),
        ]);
    }

    /**
     * Handle the OperatingUnit "updated" event.
     */
    public function updated(OperatingUnit $operatingUnit): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($operatingUnit),
            'model_id' => $operatingUnit->id,
            'old_values' => $operatingUnit->getOriginal(),
            'new_values' => $operatingUnit->getAttributes(),
        ]);
    }

    /**
     * Handle the OperatingUnit "deleted" event.
     */
    public function deleted(OperatingUnit $operatingUnit): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($operatingUnit),
            'model_id' => $operatingUnit->id,
            'old_values' => $operatingUnit->getOriginal(),
        ]);
    }

    /**
     * Handle the OperatingUnit "restored" event.
     */
    public function restored(OperatingUnit $operatingUnit): void
    {
        //
    }

    /**
     * Handle the OperatingUnit "force deleted" event.
     */
    public function forceDeleted(OperatingUnit $operatingUnit): void
    {
        //
    }
}
