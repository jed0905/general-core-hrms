<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\JobOffer;
use App\Models\User;
use App\Models\Vacancy;

/**
 * Job offers. recruitment.offer.* are organization-wide (HR). The vacancy's
 * hiring manager may view its offers; an approver may view the offers they are
 * on and decide only the current step. Every state rule is re-checked in
 * OfferService / OfferApprovalService. Role names are never checked.
 */
class OfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recruitment.offer.view')
            || $user->can('recruitment.offer.approve')
            || ($user->employee_id !== null && Vacancy::where('hiring_manager_id', $user->employee_id)->exists());
    }

    /** The offers section of an application. */
    public function viewForApplication(User $user, Application $application): bool
    {
        return $user->can('recruitment.offer.view')
            || ($user->employee_id !== null && Vacancy::whereKey($application->vacancy_id)->where('hiring_manager_id', $user->employee_id)->exists());
    }

    public function view(User $user, JobOffer $offer): bool
    {
        return $user->can('recruitment.offer.view')
            || ($user->employee_id !== null && (
                Vacancy::whereKey($offer->vacancy_id)->where('hiring_manager_id', $user->employee_id)->exists()
                || $offer->approvals()->where('approver_id', $user->employee_id)->exists()
            ));
    }

    public function create(User $user, Application $application): bool
    {
        return ! $application->isTerminal() && $user->can('recruitment.offer.create');
    }

    /** Edit the terms or submit for approval (drafts only). */
    public function update(User $user, JobOffer $offer): bool
    {
        return $offer->status === JobOffer::STATUS_DRAFT && $user->can('recruitment.offer.update');
    }

    public function approve(User $user, JobOffer $offer): bool
    {
        return $offer->status === JobOffer::STATUS_PENDING
            && $user->can('recruitment.offer.approve')
            && $user->employee_id !== null
            && $offer->currentApproval()?->approver_id === $user->employee_id;
    }

    public function reject(User $user, JobOffer $offer): bool
    {
        return $this->approve($user, $offer);
    }

    public function issue(User $user, JobOffer $offer): bool
    {
        return $offer->status === JobOffer::STATUS_APPROVED && $user->can('recruitment.offer.issue');
    }

    /** Record the candidate's acceptance or rejection. */
    public function respond(User $user, JobOffer $offer): bool
    {
        return $offer->status === JobOffer::STATUS_ISSUED && $user->can('recruitment.offer.respond');
    }

    public function withdraw(User $user, JobOffer $offer): bool
    {
        return $offer->isOpen() && $user->can('recruitment.offer.withdraw');
    }

    public function delete(User $user, JobOffer $offer): bool
    {
        return false;
    }
}
