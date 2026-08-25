<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\JobStatus;
use Illuminate\Support\Facades\Auth;

class JobStatusObserver
{
    /**
     * Handle the JobStatus "created" event.
     */
    public function created(JobStatus $jobStatus): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($jobStatus),
            'model_id' => $jobStatus->id,
            'new_values' => $jobStatus->getAttributes(),
        ]);
    }

    /**
     * Handle the JobStatus "updated" event.
     */
    public function updated(JobStatus $jobStatus): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($jobStatus),
            'model_id' => $jobStatus->id,
            'old_values' => $jobStatus->getOriginal(),
            'new_values' => $jobStatus->getAttributes(),
        ]);
    }

    /**
     * Handle the JobStatus "deleted" event.
     */
    public function deleted(JobStatus $jobStatus): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($jobStatus),
            'model_id' => $jobStatus->id,
            'old_values' => $jobStatus->getOriginal(),
        ]);
    }

    /**
     * Handle the JobStatus "restored" event.
     */
    public function restored(JobStatus $jobStatus): void
    {
        //
    }

    /**
     * Handle the JobStatus "force deleted" event.
     */
    public function forceDeleted(JobStatus $jobStatus): void
    {
        //
    }
}
