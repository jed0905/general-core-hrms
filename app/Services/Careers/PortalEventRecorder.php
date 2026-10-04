<?php

namespace App\Services\Careers;

use App\Models\RecruitmentPortalEvent;

/**
 * Writes the careers-portal audit trail. Never pass passwords, tokens or file paths in remarks.
 */
class PortalEventRecorder
{
    /**
     * @param  array{applicant_account_id?: ?int, applicant_id?: ?int, application_id?: ?int, vacancy_id?: ?int, user_id?: ?int}  $refs
     */
    public function record(string $event, array $refs, ?string $remarks = null): RecruitmentPortalEvent
    {
        return RecruitmentPortalEvent::create([
            'event' => $event,
            'applicant_account_id' => $refs['applicant_account_id'] ?? null,
            'applicant_id' => $refs['applicant_id'] ?? null,
            'application_id' => $refs['application_id'] ?? null,
            'vacancy_id' => $refs['vacancy_id'] ?? null,
            'user_id' => $refs['user_id'] ?? null,
            'remarks' => $remarks,
            'ip_address' => request()?->ip(),
            'occurred_at' => now(),
        ]);
    }
}
