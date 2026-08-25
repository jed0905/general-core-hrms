<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\Elementary;
use Illuminate\Support\Facades\Auth;

class ElementaryObserver
{
    /**
     * Handle the Elementary "created" event.
     */
    public function created(Elementary $elementary): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($elementary),
            'model_id' => $elementary->id,
            'new_values' => $elementary->getAttributes(),
        ]);
    }

    /**
     * Handle the Elementary "updated" event.
     */
    public function updated(Elementary $elementary): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($elementary),
            'model_id' => $elementary->id,
            'old_values' => $elementary->getOriginal(),
            'new_values' => $elementary->getAttributes(),
        ]);
    }

    /**
     * Handle the Elementary "deleted" event.
     */
    public function deleted(Elementary $elementary): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($elementary),
            'model_id' => $elementary->id,
            'old_values' => $elementary->getOriginal(),
        ]);
    }

    /**
     * Handle the Elementary "restored" event.
     */
    public function restored(Elementary $elementary): void
    {
        //
    }

    /**
     * Handle the Elementary "force deleted" event.
     */
    public function forceDeleted(Elementary $elementary): void
    {
        //
    }
}
