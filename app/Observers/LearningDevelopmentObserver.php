<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\LearningDevelopment;
use Illuminate\Support\Facades\Auth;

class LearningDevelopmentObserver
{
    /**
     * Handle the LearningDevelopment "created" event.
     */
    public function created(LearningDevelopment $learningDevelopment): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($learningDevelopment),
            'model_id' => $learningDevelopment->id,
            'new_values' => $learningDevelopment->getAttributes(),
        ]);
    }

    /**
     * Handle the LearningDevelopment "updated" event.
     */
    public function updated(LearningDevelopment $learningDevelopment): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($learningDevelopment),
            'model_id' => $learningDevelopment->id,
            'old_values' => $learningDevelopment->getOriginal(),
            'new_values' => $learningDevelopment->getAttributes(),
        ]);
    }

    /**
     * Handle the LearningDevelopment "deleted" event.
     */
    public function deleted(LearningDevelopment $learningDevelopment): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($learningDevelopment),
            'model_id' => $learningDevelopment->id,
            'old_values' => $learningDevelopment->getOriginal(),
        ]);
    }

    /**
     * Handle the LearningDevelopment "restored" event.
     */
    public function restored(LearningDevelopment $learningDevelopment): void
    {
        //
    }

    /**
     * Handle the LearningDevelopment "force deleted" event.
     */
    public function forceDeleted(LearningDevelopment $learningDevelopment): void
    {
        //
    }
}
