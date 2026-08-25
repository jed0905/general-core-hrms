<?php

namespace App\Observers;

use App\Models\User;
use App\Models\AuditTrail;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        AuditTrail::create([
            'user_id' => auth()->check() ? Auth::id() : null,
            'action' => 'created',
            'model_type' => get_class($user),
            'model_id' => $user->id,
            'new_values' => $user->getAttributes(),
        ]);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => get_class($user),
            'model_id' => $user->id,
            'old_values' => $user->getOriginal(),
            'new_values' => $user->getAttributes(),
        ]);
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'model_type' => get_class($user),
            'model_id' => $user->id,
            'old_values' => $user->getOriginal(),
        ]);
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
