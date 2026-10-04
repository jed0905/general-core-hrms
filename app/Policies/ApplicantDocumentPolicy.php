<?php

namespace App\Policies;

use App\Models\ApplicantDocument;
use App\Models\Application;
use App\Models\ApplicationInterview;
use App\Models\User;

/**
 * Viewing an applicant document: the applicant registry permission, being the
 * hiring manager of a vacancy the document was submitted to, or sitting on a
 * (not cancelled) interview panel of an application it was submitted with.
 */
class ApplicantDocumentPolicy
{
    public function view(User $user, ApplicantDocument $document): bool
    {
        if ($user->can('recruitment.applicant.view')) {
            return true;
        }

        if ($user->employee_id === null) {
            return false;
        }

        return Application::query()
            ->whereHas('documents', fn ($q) => $q->whereKey($document->id))
            ->where(fn ($q) => $q
                ->whereHas('vacancy', fn ($v) => $v->where('hiring_manager_id', $user->employee_id))
                ->orWhereHas('interviews', fn ($i) => $i
                    ->where('status', '!=', ApplicationInterview::STATUS_CANCELLED)
                    ->whereHas('panelists', fn ($p) => $p->where('employee_id', $user->employee_id))))
            ->exists();
    }

    public function delete(User $user, ApplicantDocument $document): bool
    {
        return $user->can('recruitment.applicant.update');
    }
}
