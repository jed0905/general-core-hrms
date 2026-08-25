<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\SalaryGrade;
use Illuminate\Support\Facades\Auth;

class SalaryGradeObserver
{
    /**
     * Handle the SalaryGrade "created" event.
     */
    public function created(SalaryGrade $salaryGrade): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($salaryGrade),
            'model_id' => $salaryGrade->id,
            'new_values' => $salaryGrade->getAttributes(),
        ]);
    }

    /**
     * Handle the SalaryGrade "updated" event.
     */
    public function updated(SalaryGrade $salaryGrade): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($salaryGrade),
            'model_id' => $salaryGrade->id,
            'old_values' => $salaryGrade->getOriginal(),
            'new_values' => $salaryGrade->getAttributes(),
        ]);
    }

    /**
     * Handle the SalaryGrade "deleted" event.
     */
    public function deleted(SalaryGrade $salaryGrade): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($salaryGrade),
            'model_id' => $salaryGrade->id,
            'old_values' => $salaryGrade->getOriginal(),
        ]);
    }

    /**
     * Handle the SalaryGrade "restored" event.
     */
    public function restored(SalaryGrade $salaryGrade): void
    {
        //
    }

    /**
     * Handle the SalaryGrade "force deleted" event.
     */
    public function forceDeleted(SalaryGrade $salaryGrade): void
    {
        //
    }
}
