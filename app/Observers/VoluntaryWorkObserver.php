<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\VoluntaryWork;
use Illuminate\Support\Facades\Auth;

class VoluntaryWorkObserver
{
    /**
     * Handle the VoluntaryWork "created" event.
     */
    public function created(VoluntaryWork $voluntaryWork): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($voluntaryWork),
            'model_id' => $voluntaryWork->id,
            'new_values' => $voluntaryWork->getAttributes(),
        ]);
    }

    /**
     * Handle the VoluntaryWork "updated" event.
     */
    public function updated(VoluntaryWork $voluntaryWork): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($voluntaryWork),
            'model_id' => $voluntaryWork->id,
            'old_values' => $voluntaryWork->getOriginal(),
            'new_values' => $voluntaryWork->getAttributes(),
        ]);
    }

    /**
     * Handle the VoluntaryWork "deleted" event.
     */
    public function deleted(VoluntaryWork $voluntaryWork): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($voluntaryWork),
            'model_id' => $voluntaryWork->id,
            'old_values' => $voluntaryWork->getOriginal(),
        ]);
    }

    /**
     * Handle the VoluntaryWork "restored" event.
     */
    public function restored(VoluntaryWork $voluntaryWork): void
    {
        //
    }

    /**
     * Handle the VoluntaryWork "force deleted" event.
     */
    public function forceDeleted(VoluntaryWork $voluntaryWork): void
    {
        //
    }
}
