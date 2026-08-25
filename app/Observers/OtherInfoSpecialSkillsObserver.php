<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\OtherInfoSpecialSkills;
use Illuminate\Support\Facades\Auth;

class OtherInfoSpecialSkillsObserver
{
    /**
     * Handle the OtherInfoSpecialSkills "created" event.
     */
    public function created(OtherInfoSpecialSkills $otherInfoSpecialSkills): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($otherInfoSpecialSkills),
            'model_id' => $otherInfoSpecialSkills->id,
            'new_values' => $otherInfoSpecialSkills->getAttributes(),
        ]);
    }

    /**
     * Handle the OtherInfoSpecialSkills "updated" event.
     */
    public function updated(OtherInfoSpecialSkills $otherInfoSpecialSkills): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($otherInfoSpecialSkills),
            'model_id' => $otherInfoSpecialSkills->id,
            'old_values' => $otherInfoSpecialSkills->getOriginal(),
            'new_values' => $otherInfoSpecialSkills->getAttributes(),
        ]);
    }

    /**
     * Handle the OtherInfoSpecialSkills "deleted" event.
     */
    public function deleted(OtherInfoSpecialSkills $otherInfoSpecialSkills): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($otherInfoSpecialSkills),
            'model_id' => $otherInfoSpecialSkills->id,
            'old_values' => $otherInfoSpecialSkills->getOriginal(),
        ]);
    }

    /**
     * Handle the OtherInfoSpecialSkills "restored" event.
     */
    public function restored(OtherInfoSpecialSkills $otherInfoSpecialSkills): void
    {
        //
    }

    /**
     * Handle the OtherInfoSpecialSkills "force deleted" event.
     */
    public function forceDeleted(OtherInfoSpecialSkills $otherInfoSpecialSkills): void
    {
        //
    }
}
