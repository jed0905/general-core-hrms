<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\Position;
use Illuminate\Support\Facades\Auth;

class PositionObserver
{
    /**
     * Handle the Position "created" event.
     */
    public function created(Position $position): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($position),
            'model_id' => $position->id,
            'new_values' => $position->getAttributes(),
        ]);
    }

    /**
     * Handle the Position "updated" event.
     */
    public function updated(Position $position): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($position),
            'model_id' => $position->id,
            'old_values' => $position->getOriginal(),
            'new_values' => $position->getAttributes(),
        ]);
    }

    /**
     * Handle the Position "deleted" event.
     */
    public function deleted(Position $position): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($position),
            'model_id' => $position->id,
            'old_values' => $position->getOriginal(),
        ]);
    }

    /**
     * Handle the Position "restored" event.
     */
    public function restored(Position $position): void
    {
        //
    }

    /**
     * Handle the Position "force deleted" event.
     */
    public function forceDeleted(Position $position): void
    {
        //
    }
}
