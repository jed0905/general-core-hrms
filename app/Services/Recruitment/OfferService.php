<?php

namespace App\Services\Recruitment;

use App\Models\Application;
use App\Models\ApplicationSelection;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\JobOffer;
use App\Models\JobOfferEvent;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\NumberSequence;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\NumberSequenceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Job offers for selected candidates: draft, submit for approval, issue,
 * record the candidate's response, expire and withdraw. Approval decisions are
 * OfferApprovalService's. Every action is one transaction that locks the
 * application row and then the offer row (the same order everywhere) and writes
 * an offer event.
 *
 * Nothing here creates or changes employee, employee-number, movement or
 * payroll records: an accepted offer is the input Phase 5 (hiring) will use.
 */
class OfferService
{
    public function __construct(
        protected NumberSequenceService $numberSequences,
        protected SelectionService $selections,
        protected OfferApprovalService $approvals
    ) {}

    /**
     * Without recruitment.offer.view: offers on vacancies the user is hiring
     * manager for, plus offers the user is an approver on.
     */
    public function visibleTo(User $user): Builder
    {
        return JobOffer::query()->when(! $user->can('recruitment.offer.view'), fn (Builder $q) => $user->employee_id
            ? $q->where(fn ($w) => $w
                ->whereHas('vacancy', fn ($v) => $v->where('hiring_manager_id', $user->employee_id))
                ->orWhereHas('approvals', fn ($a) => $a->where('approver_id', $user->employee_id)))
            : $q->whereRaw('1 = 0'));
    }

    public function getPaginatedOffers(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $awaiting = ($filters['awaiting'] ?? null) && $user->employee_id
            ? $this->approvals->awaitingDecisionBy(Employee::findOrFail($user->employee_id))->select('id')
            : null;

        return $this->visibleTo($user)
            ->with(['applicant:id,applicant_number,first_name,last_name', 'vacancy:id,vacancy_number,title'])
            ->when($awaiting, fn ($q) => $q->whereIn('id', $awaiting))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('offer_number', 'like', "%{$search}%")
                ->orWhereHas('applicant', fn ($a) => $a->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $terms  validated offer terms
     */
    public function createDraft(Application $application, array $terms, User $actor): JobOffer
    {
        try {
            return DB::transaction(function () use ($application, $terms, $actor) {
                $application = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
                $this->authorize($actor, 'recruitment.offer.create');

                $selection = $this->assertSelected($application);
                $vacancy = Vacancy::findOrFail($application->vacancy_id);
                $this->assertVacancyLive($vacancy);
                $this->assertNoActiveOffer($application);
                $this->assertDates($terms);

                $offer = JobOffer::create([
                    'offer_number' => $this->numberSequences->next(
                        NumberSequence::JOB_OFFER_NUMBER,
                        fn (string $candidate) => JobOffer::where('offer_number', $candidate)->exists()
                    ),
                    'application_id' => $application->id,
                    'vacancy_id' => $vacancy->id,
                    'applicant_id' => $application->applicant_id,
                    'application_selection_id' => $selection->id,
                    'status' => JobOffer::STATUS_DRAFT,
                    'department_id' => $vacancy->department_id,
                    'department_name' => Department::whereKey($vacancy->department_id)->value('name'),
                    'created_by' => $actor->id,
                ] + $this->terms($terms));

                $this->approvals->event($offer, JobOfferEvent::CREATED, null, JobOffer::STATUS_DRAFT, $actor);
                $application->update(['last_activity_at' => now()]);

                return $offer;
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request created the application's active offer first (database-enforced).
            if (str_contains($e->getMessage(), 'job_offers_one_active_unique') || str_contains($e->getMessage(), 'job_offers.active_application_id')) {
                throw ValidationException::withMessages(['offer' => ['This application already has an active offer.']]);
            }
            throw $e;
        }
    }

    public function updateDraft(JobOffer $offer, array $terms, User $actor): JobOffer
    {
        return DB::transaction(function () use ($offer, $terms, $actor) {
            [, $offer] = $this->lock($offer, [JobOffer::STATUS_DRAFT]);
            $this->authorize($actor, 'recruitment.offer.update');
            $this->assertDates($terms);

            $offer->update($this->terms($terms) + ['updated_by' => $actor->id]);
            $this->approvals->event($offer, JobOfferEvent::UPDATED, JobOffer::STATUS_DRAFT, JobOffer::STATUS_DRAFT, $actor);

            return $offer;
        });
    }

    /**
     * Freeze the terms and start the approval chain (snapshotted now).
     */
    public function submit(JobOffer $offer, ?string $remarks, User $actor): JobOffer
    {
        return DB::transaction(function () use ($offer, $remarks, $actor) {
            [$application, $offer] = $this->lock($offer, [JobOffer::STATUS_DRAFT]);
            $this->authorize($actor, 'recruitment.offer.update');
            $this->assertSelected($application);
            $this->assertVacancyLive(Vacancy::findOrFail($offer->vacancy_id));
            if ($offer->isPastExpiry()) {
                throw ValidationException::withMessages(['expiry_date' => ['The expiry date has passed. Edit the draft first.']]);
            }

            $offer->update(['status' => JobOffer::STATUS_PENDING, 'submitted_at' => now(), 'submitted_by' => $actor->id]);
            $this->approvals->createApprovals($offer, $actor);
            $this->approvals->event($offer, JobOfferEvent::SUBMITTED, JobOffer::STATUS_DRAFT, JobOffer::STATUS_PENDING, $actor, $remarks);

            return $offer;
        });
    }

    /**
     * Only a fully approved offer that hasn't passed its expiry date.
     */
    public function issue(JobOffer $offer, ?string $remarks, User $actor): JobOffer
    {
        return DB::transaction(function () use ($offer, $remarks, $actor) {
            [$application, $offer] = $this->lock($offer, [JobOffer::STATUS_APPROVED]);
            $this->authorize($actor, 'recruitment.offer.issue');
            $this->assertSelected($application);
            $this->assertVacancyLive(Vacancy::findOrFail($offer->vacancy_id));
            if ($offer->isPastExpiry()) {
                throw ValidationException::withMessages(['status' => ['The offer\'s expiry date has passed; withdraw it and prepare a new offer.']]);
            }

            $offer->update(['status' => JobOffer::STATUS_ISSUED, 'offer_date' => today(), 'issued_at' => now(), 'issued_by' => $actor->id]);
            $this->approvals->event($offer, JobOfferEvent::ISSUED, JobOffer::STATUS_APPROVED, JobOffer::STATUS_ISSUED, $actor, $remarks);
            $application->update(['last_activity_at' => now()]);

            return $offer;
        });
    }

    /**
     * Record the candidate's answer (HR-side; there is no applicant portal yet).
     * An offer past its expiry date is expired instead and the response refused.
     */
    public function respond(JobOffer $offer, string $response, ?string $remarks, User $actor): JobOffer
    {
        if ($this->expireIfDue($offer)) {
            throw ValidationException::withMessages(['status' => ['This offer expired; the response can no longer be recorded.']]);
        }

        return DB::transaction(function () use ($offer, $response, $remarks, $actor) {
            [$application, $offer] = $this->lock($offer, [JobOffer::STATUS_ISSUED]);
            $this->authorize($actor, 'recruitment.offer.respond');
            if ($application->isTerminal()) {
                throw ValidationException::withMessages(['status' => ["This application is {$application->status}."]]);
            }
            if ($offer->isPastExpiry()) {
                throw ValidationException::withMessages(['status' => ['This offer expired; the response can no longer be recorded.']]);
            }

            $accepted = $response === JobOffer::RESPONSE_ACCEPTED;
            $status = $accepted ? JobOffer::STATUS_ACCEPTED : JobOffer::STATUS_DECLINED;

            $offer->update([
                'status' => $status,
                'response' => $response,
                'responded_at' => now(),
                'response_recorded_by' => $actor->id,
                'response_remarks' => $remarks,
            ]);
            $this->approvals->event($offer, $accepted ? JobOfferEvent::CANDIDATE_ACCEPTED : JobOfferEvent::CANDIDATE_DECLINED, JobOffer::STATUS_ISSUED, $status, $actor, $remarks);
            $application->update(['last_activity_at' => now()]);

            return $offer;
        });
    }

    public function withdraw(JobOffer $offer, string $reason, User $actor): JobOffer
    {
        return DB::transaction(function () use ($offer, $reason, $actor) {
            [, $offer] = $this->lock($offer, JobOffer::OPEN);
            $this->authorize($actor, 'recruitment.offer.withdraw');
            $from = $offer->status;

            $offer->update(['status' => JobOffer::STATUS_WITHDRAWN, 'withdrawn_at' => now(), 'withdrawn_by' => $actor->id, 'withdrawal_reason' => $reason]);
            $this->approvals->skipPending($offer);
            $this->approvals->event($offer, JobOfferEvent::WITHDRAWN, $from, JobOffer::STATUS_WITHDRAWN, $actor, $reason);

            return $offer;
        });
    }

    /**
     * Mark an issued offer expired once its expiry date has passed. Returns true when it did.
     */
    public function expireIfDue(JobOffer $offer): bool
    {
        return DB::transaction(function () use ($offer) {
            $offer = JobOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if ($offer->status !== JobOffer::STATUS_ISSUED || ! $offer->isPastExpiry()) {
                return false;
            }

            $offer->update(['status' => JobOffer::STATUS_EXPIRED, 'expired_at' => now()]);
            $this->approvals->event($offer, JobOfferEvent::EXPIRED, JobOffer::STATUS_ISSUED, JobOffer::STATUS_EXPIRED, null, "Not accepted by {$offer->expiry_date->toDateString()}.");

            return true;
        });
    }

    /**
     * Expire every issued offer past its expiry date (recruitment:expire-offers).
     */
    public function expireDue(): int
    {
        return JobOffer::where('status', JobOffer::STATUS_ISSUED)->where('expiry_date', '<', today()->toDateString())
            ->pluck('id')
            ->filter(fn ($id) => $this->expireIfDue(JobOffer::find($id)))
            ->count();
    }

    // ----------------------------------------------------------------- internals

    /**
     * @return array{0: Application, 1: JobOffer}
     */
    protected function lock(JobOffer $offer, array $allowed): array
    {
        $application = Application::whereKey($offer->application_id)->lockForUpdate()->firstOrFail();
        $offer = JobOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();

        if (! in_array($offer->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => ["This offer is {$offer->status}; that action is no longer possible."]]);
        }

        return [$application, $offer];
    }

    protected function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw ValidationException::withMessages(['status' => ['You are not allowed to perform this action.']]);
        }
    }

    /** The application must be live and its current selection decision "selected". */
    protected function assertSelected(Application $application): ApplicationSelection
    {
        if ($application->isTerminal()) {
            throw ValidationException::withMessages(['offer' => ["This application is {$application->status}."]]);
        }

        $selection = $this->selections->current($application);
        if (! $selection?->isSelected()) {
            throw ValidationException::withMessages(['offer' => ['Only a selected candidate can receive an offer.']]);
        }

        return $selection;
    }

    protected function assertVacancyLive(Vacancy $vacancy): void
    {
        if (in_array($vacancy->status, [Vacancy::STATUS_DRAFT, Vacancy::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages(['offer' => ["The vacancy is {$vacancy->status}."]]);
        }
    }

    protected function assertNoActiveOffer(Application $application): void
    {
        $active = JobOffer::where('application_id', $application->id)->whereIn('status', JobOffer::ACTIVE)->first();

        if ($active) {
            throw ValidationException::withMessages(['offer' => ["This application already has offer {$active->offer_number} ({$active->status}). Withdraw it before preparing another."]]);
        }
    }

    protected function assertDates(array $terms): void
    {
        if ($terms['expiry_date'] < today()->toDateString()) {
            throw ValidationException::withMessages(['expiry_date' => ['The expiry date cannot be in the past.']]);
        }
        if ($terms['proposed_start_date'] < $terms['expiry_date']) {
            throw ValidationException::withMessages(['proposed_start_date' => ['The proposed start date must be on or after the offer\'s expiry date.']]);
        }
    }

    /**
     * Terms with the names snapshotted from the master tables.
     */
    protected function terms(array $data): array
    {
        $terms = Arr::only($data, ['job_title_id', 'employment_status_id', 'location_id', 'proposed_start_date', 'expiry_date', 'base_salary', 'salary_frequency', 'currency', 'benefits', 'remarks']);
        $location = ! empty($data['location_id']) ? Location::find($data['location_id']) : null;

        return array_merge(array_fill_keys(['employment_status_id', 'location_id', 'benefits', 'remarks'], null), $terms, [
            'position_title' => JobTitle::whereKey($data['job_title_id'])->value('job_title'),
            'employment_type' => ! empty($data['employment_status_id']) ? EmploymentStatus::whereKey($data['employment_status_id'])->value('name') : null,
            'work_location' => $data['work_location'] ?? ($location ? trim(implode(', ', array_filter([$location->address, $location->city]))) : null),
            'currency' => strtoupper($data['currency']),
        ]);
    }
}
