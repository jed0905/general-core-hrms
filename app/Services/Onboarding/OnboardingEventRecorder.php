<?php

namespace App\Services\Onboarding;

use App\Models\Onboarding;
use App\Models\OnboardingEvent;
use App\Models\OnboardingTask;
use App\Models\User;

/**
 * Writes the immutable onboarding audit trail (meaningful actions only, never page views).
 */
class OnboardingEventRecorder
{
    public function record(Onboarding $onboarding, string $event, ?User $actor, ?string $remarks = null, ?OnboardingTask $task = null): OnboardingEvent
    {
        return OnboardingEvent::create([
            'onboarding_id' => $onboarding->id,
            'onboarding_task_id' => $task?->id,
            'event' => $event,
            'actor_id' => $actor?->id,
            'remarks' => $remarks,
            'occurred_at' => now(),
        ]);
    }
}
