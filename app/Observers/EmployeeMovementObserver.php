<?php

namespace App\Observers;

use App\Models\AuditTrail;
use App\Models\EmployeeMovement;
use Illuminate\Support\Facades\Auth;

/**
 * Audit trail for movement records (who changed what, when). The movement
 * itself is the employment history; this logs changes to that record.
 */
class EmployeeMovementObserver
{
    public function created(EmployeeMovement $movement): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'created',
            'model_type' => get_class($movement),
            'model_id' => $movement->id,
            'new_values' => $movement->getAttributes(),
        ]);
    }

    public function updated(EmployeeMovement $movement): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($movement),
            'model_id' => $movement->id,
            'old_values' => $movement->getOriginal(),
            'new_values' => $movement->getAttributes(),
        ]);
    }
}
