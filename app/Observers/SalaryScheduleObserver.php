<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\SalarySchedule;
use Illuminate\Support\Facades\Auth;

class SalaryScheduleObserver
{
    /**
     * Handle the SalarySchedule "created" event.
     */
    public function created(SalarySchedule $salarySchedule): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($salarySchedule),
            'model_id' => $salarySchedule->id,
            'new_values' => $salarySchedule->getAttributes(),
        ]);
    }

    /**
     * Handle the SalarySchedule "updated" event.
     */
    public function updated(SalarySchedule $salarySchedule): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($salarySchedule),
            'model_id' => $salarySchedule->id,
            'old_values' => $salarySchedule->getOriginal(),
            'new_values' => $salarySchedule->getAttributes(),
        ]);
    }

    /**
     * Handle the SalarySchedule "deleted" event.
     */
    public function deleted(SalarySchedule $salarySchedule): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($salarySchedule),
            'model_id' => $salarySchedule->id,
            'old_values' => $salarySchedule->getOriginal(),
        ]);
    }

    /**
     * Handle the SalarySchedule "restored" event.
     */
    public function restored(SalarySchedule $salarySchedule): void
    {
        //
    }

    /**
     * Handle the SalarySchedule "force deleted" event.
     */
    public function forceDeleted(SalarySchedule $salarySchedule): void
    {
        //
    }
}
