<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\SalaryStep;
use Illuminate\Support\Facades\Auth;

class SalaryStepObserver
{
    /**
     * Handle the SalaryStep "created" event.
     */
    public function created(SalaryStep $salaryStep): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($salaryStep),
            'model_id' => $salaryStep->id,
            'new_values' => $salaryStep->getAttributes(),
        ]);
    }

    /**
     * Handle the SalaryStep "updated" event.
     */
    public function updated(SalaryStep $salaryStep): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($salaryStep),
            'model_id' => $salaryStep->id,
            'old_values' => $salaryStep->getOriginal(),
            'new_values' => $salaryStep->getAttributes(),
        ]);
    }

    /**
     * Handle the SalaryStep "deleted" event.
     */
    public function deleted(SalaryStep $salaryStep): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($salaryStep),
            'model_id' => $salaryStep->id,
            'old_values' => $salaryStep->getOriginal(),
        ]);
    }

    /**
     * Handle the SalaryStep "restored" event.
     */
    public function restored(SalaryStep $salaryStep): void
    {
        //
    }

    /**
     * Handle the SalaryStep "force deleted" event.
     */
    public function forceDeleted(SalaryStep $salaryStep): void
    {
        //
    }
}
