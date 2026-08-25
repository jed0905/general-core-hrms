<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\WorkExperience;
use Illuminate\Support\Facades\Auth;

class WorkExperienceObserver
{
    /**
     * Handle the WorkExperience "created" event.
     */
    public function created(WorkExperience $workExperience): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($workExperience),
            'model_id' => $workExperience->id,
            'new_values' => $workExperience->getAttributes(),
        ]);
    }

    /**
     * Handle the WorkExperience "updated" event.
     */
    public function updated(WorkExperience $workExperience): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($workExperience),
            'model_id' => $workExperience->id,
            'old_values' => $workExperience->getOriginal(),
            'new_values' => $workExperience->getAttributes(),
        ]);
    }

    /**
     * Handle the WorkExperience "deleted" event.
     */
    public function deleted(WorkExperience $workExperience): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($workExperience),
            'model_id' => $workExperience->id,
            'old_values' => $workExperience->getOriginal(),
        ]);
    }

    /**
     * Handle the WorkExperience "restored" event.
     */
    public function restored(WorkExperience $workExperience): void
    {
        //
    }

    /**
     * Handle the WorkExperience "force deleted" event.
     */
    public function forceDeleted(WorkExperience $workExperience): void
    {
        //
    }
}
