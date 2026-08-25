<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\GraduateStudy;
use Illuminate\Support\Facades\Auth;

class GraduateStudyObserver
{
    /**
     * Handle the GraduateStudy "created" event.
     */
    public function created(GraduateStudy $graduateStudy): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($graduateStudy),
            'model_id' => $graduateStudy->id,
            'new_values' => $graduateStudy->getAttributes(),
        ]);
    }

    /**
     * Handle the GraduateStudy "updated" event.
     */
    public function updated(GraduateStudy $graduateStudy): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($graduateStudy),
            'model_id' => $graduateStudy->id,
            'old_values' => $graduateStudy->getOriginal(),
            'new_values' => $graduateStudy->getAttributes(),
        ]);
    }

    /**
     * Handle the GraduateStudy "deleted" event.
     */
    public function deleted(GraduateStudy $graduateStudy): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($graduateStudy),
            'model_id' => $graduateStudy->id,
            'old_values' => $graduateStudy->getOriginal(),
        ]);
    }

    /**
     * Handle the GraduateStudy "restored" event.
     */
    public function restored(GraduateStudy $graduateStudy): void
    {
        //
    }

    /**
     * Handle the GraduateStudy "force deleted" event.
     */
    public function forceDeleted(GraduateStudy $graduateStudy): void
    {
        //
    }
}
