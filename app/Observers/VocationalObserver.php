<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\Vocational;
use Illuminate\Support\Facades\Auth;

class VocationalObserver
{
    /**
     * Handle the Vocational "created" event.
     */
    public function created(Vocational $vocational): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($vocational),
            'model_id' => $vocational->id,
            'new_values' => $vocational->getAttributes(),
        ]);
    }

    /**
     * Handle the Vocational "updated" event.
     */
    public function updated(Vocational $vocational): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($vocational),
            'model_id' => $vocational->id,
            'old_values' => $vocational->getOriginal(),
            'new_values' => $vocational->getAttributes(),
        ]);
    }

    /**
     * Handle the Vocational "deleted" event.
     */
    public function deleted(Vocational $vocational): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($vocational),
            'model_id' => $vocational->id,
            'old_values' => $vocational->getOriginal(),
        ]);
    }

    /**
     * Handle the Vocational "restored" event.
     */
    public function restored(Vocational $vocational): void
    {
        //
    }

    /**
     * Handle the Vocational "force deleted" event.
     */
    public function forceDeleted(Vocational $vocational): void
    {
        //
    }
}
