<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\Secondary;
use Illuminate\Support\Facades\Auth;

class SecondaryObserver
{
    /**
     * Handle the Secondary "created" event.
     */
    public function created(Secondary $secondary): void
    {
         AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($secondary),
            'model_id' => $secondary->id,
            'new_values' => $secondary->getAttributes(),
        ]);
    }

    /**
     * Handle the Secondary "updated" event.
     */
    public function updated(Secondary $secondary): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($secondary),
            'model_id' => $secondary->id,
            'old_values' => $secondary->getOriginal(),
            'new_values' => $secondary->getAttributes(),
        ]);
    }

    /**
     * Handle the Secondary "deleted" event.
     */
    public function deleted(Secondary $secondary): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($secondary),
            'model_id' => $secondary->id,
            'old_values' => $secondary->getOriginal(),
        ]);
    }

    /**
     * Handle the Secondary "restored" event.
     */
    public function restored(Secondary $secondary): void
    {
        //
    }

    /**
     * Handle the Secondary "force deleted" event.
     */
    public function forceDeleted(Secondary $secondary): void
    {
        //
    }
}
