<?php

namespace App\Services\Recruitment;

use App\Models\ApprovalWorkflow;
use App\Models\Employee;
use App\Models\JobOffer;
use App\Models\JobOfferApproval;
use App\Models\JobOfferEvent;
use App\Models\User;
use App\Services\ApprovalChainResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approval instances for job offers: the same generic workflow + resolver as
 * job requisitions (module "job_offer"), snapshotted into job_offer_approvals.
 *
 * At submission the configured chain is resolved for the submitting employee
 * and copied (approver, order, type, workflow name); later workflow edits never
 * touch it. Approvals run strictly in approval_order; only the approver on the
 * lowest pending step may act, and every decision locks the offer and its
 * current step and re-checks state and actor inside the transaction.
 */
class OfferApprovalService
{
    public const APPROVE_PERMISSION = 'recruitment.offer.approve';

    public function __construct(protected ApprovalChainResolver $resolver) {}

    /**
     * Must be called inside the submit transaction, with the offer locked.
     */
    public function createApprovals(JobOffer $offer, User $submitter): void
    {
        $workflow = $this->resolver->activeWorkflow(ApprovalWorkflow::MODULE_JOB_OFFER);

        if (! $workflow) {
            throw ValidationException::withMessages(['approval' => ['No active offer approval workflow is configured. Please contact HR.']]);
        }

        $subject = $submitter->employee_id ? Employee::find($submitter->employee_id) : null;
        if (! $subject) {
            throw ValidationException::withMessages(['approval' => ['Your account must be linked to an employee to submit an offer: the approval chain starts from your supervisor.']]);
        }

        foreach ($this->resolver->resolve($workflow, $subject, $this->excludedApprovers($offer, $submitter), self::APPROVE_PERMISSION) as $order => $link) {
            $offer->approvals()->create([
                'approval_workflow_id' => $workflow->id,
                'approval_workflow_step_id' => $link['step']->id,
                'workflow_name' => $workflow->name,
                'approval_order' => $order + 1,
                'approver_type' => $link['step']->approver_type,
                'is_required' => $link['step']->is_required,
                'approver_id' => $link['approver']->id,
                'approver_name' => trim($link['approver']->emp_first_name.' '.$link['approver']->emp_last_name),
                'status' => JobOfferApproval::STATUS_PENDING,
            ]);
        }
    }

    public function approve(JobOffer $offer, User $actor, ?string $remarks = null): JobOffer
    {
        return DB::transaction(function () use ($offer, $actor, $remarks) {
            [$offer, $step] = $this->lockForDecision($offer, $actor);

            $step->update(['status' => JobOfferApproval::STATUS_APPROVED, 'acted_at' => now(), 'acted_by' => $actor->id, 'remarks' => $remarks]);

            if (JobOfferApproval::where('job_offer_id', $offer->id)->where('status', JobOfferApproval::STATUS_PENDING)->exists()) {
                $this->event($offer, JobOfferEvent::STEP_APPROVED, $offer->status, $offer->status, $actor, $remarks ?: "Step {$step->approval_order} approved.");
            } else {
                $offer->update(['status' => JobOffer::STATUS_APPROVED, 'approved_at' => now()]);
                $this->event($offer, JobOfferEvent::APPROVED, JobOffer::STATUS_PENDING, JobOffer::STATUS_APPROVED, $actor, $remarks);
            }

            return $offer->fresh();
        });
    }

    public function reject(JobOffer $offer, User $actor, string $remarks): JobOffer
    {
        return DB::transaction(function () use ($offer, $actor, $remarks) {
            [$offer, $step] = $this->lockForDecision($offer, $actor);

            $step->update(['status' => JobOfferApproval::STATUS_REJECTED, 'acted_at' => now(), 'acted_by' => $actor->id, 'remarks' => $remarks]);
            $this->skipPending($offer);

            $offer->update(['status' => JobOffer::STATUS_REJECTED, 'rejected_at' => now()]);
            $this->event($offer, JobOfferEvent::APPROVAL_REJECTED, JobOffer::STATUS_PENDING, JobOffer::STATUS_REJECTED, $actor, $remarks);

            return $offer->fresh();
        });
    }

    /** Remaining pending steps no longer apply (offer rejected or withdrawn). */
    public function skipPending(JobOffer $offer): void
    {
        JobOfferApproval::where('job_offer_id', $offer->id)
            ->where('status', JobOfferApproval::STATUS_PENDING)
            ->update(['status' => JobOfferApproval::STATUS_SKIPPED]);
    }

    /**
     * Offers whose current step belongs to this employee.
     */
    public function awaitingDecisionBy(Employee $approver): Builder
    {
        return JobOffer::query()
            ->where('status', JobOffer::STATUS_PENDING)
            ->whereHas('approvals', fn ($q) => $q
                ->where('approver_id', $approver->id)
                ->where('status', JobOfferApproval::STATUS_PENDING)
                ->whereNotExists(fn ($earlier) => $earlier
                    ->selectRaw('1')
                    ->from('job_offer_approvals as earlier')
                    ->whereColumn('earlier.job_offer_id', 'job_offer_approvals.job_offer_id')
                    ->whereColumn('earlier.approval_order', '<', 'job_offer_approvals.approval_order')
                    ->where('earlier.status', JobOfferApproval::STATUS_PENDING)));
    }

    public function event(JobOffer $offer, string $event, ?string $from, string $to, ?User $actor, ?string $remarks = null): void
    {
        JobOfferEvent::create([
            'job_offer_id' => $offer->id,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'remarks' => $remarks,
            'occurred_at' => now(),
        ]);
    }

    /** The submitter and the offer's creator never approve their own offer. */
    protected function excludedApprovers(JobOffer $offer, User $submitter): array
    {
        return array_values(array_unique(array_filter([
            $submitter->employee_id,
            User::whereKey($offer->created_by)->value('employee_id'),
        ])));
    }

    /**
     * Lock the offer and its current step, and re-check that this actor may decide now.
     *
     * @return array{0: JobOffer, 1: JobOfferApproval}
     */
    protected function lockForDecision(JobOffer $offer, User $actor): array
    {
        $offer = JobOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();
        $step = JobOfferApproval::where('job_offer_id', $offer->id)
            ->where('status', JobOfferApproval::STATUS_PENDING)
            ->orderBy('approval_order')
            ->lockForUpdate()
            ->first();

        if ($offer->status !== JobOffer::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => ["This offer is {$offer->status}, not awaiting approval."]]);
        }

        if (! $step || $actor->employee_id === null || $step->approver_id !== $actor->employee_id || ! $actor->can(self::APPROVE_PERMISSION)) {
            throw ValidationException::withMessages(['approval' => ['You are not the approver for the current step of this offer.']]);
        }

        $submitter = User::find($offer->submitted_by);
        if (in_array($actor->employee_id, $submitter ? $this->excludedApprovers($offer, $submitter) : [], true)) {
            throw ValidationException::withMessages(['approval' => ['You cannot approve an offer you prepared or submitted.']]);
        }

        return [$offer, $step];
    }
}
