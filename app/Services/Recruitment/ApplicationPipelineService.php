<?php

namespace App\Services\Recruitment;

use App\Models\Application;
use App\Models\ApplicationAssessment;
use App\Models\ApplicationConversion;
use App\Models\ApplicationInterview;
use App\Models\ApplicationScreening;
use App\Models\ApplicationStageHistory;
use App\Models\JobOffer;
use App\Models\RecruitmentStage;
use App\Models\RejectionReason;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The ONLY place an application's current stage or status changes.
 *
 * Every action locks the application row, checks the stage the user acted on
 * is still current (so a stale screen can't double-move it), re-checks the
 * transition and the actor's permission, then updates the application and
 * writes an immutable history row, all in one transaction.
 *
 * Phase 2 transitions: applied → screening (advance), screening → shortlisted
 * (shortlist, needs a passed screening), reject and withdraw from any
 * non-terminal state. Rejected and withdrawn are terminal.
 *
 * Phase 3 transitions (advance; the status stays "shortlisted"):
 * shortlisted → interview, interview → assessment (needs a completed
 * interview), assessment → evaluation (needs a completed assessment and none
 * still open). Creating interviews, assessments or scorecards never moves an
 * application; only these explicit actions do. Evaluation is the last stage.
 *
 * Phase 4: selection is a decision at the Evaluation stage (SelectionService),
 * not a stage. Rejecting or withdrawing an application is refused while one of
 * its offers is still in play (withdraw the offer first), and releases the
 * vacancy opening a selected candidate held.
 */
class ApplicationPipelineService
{
    /** Vacancies in these states accept no forward moves (reject/withdraw still allowed). */
    private const FROZEN_VACANCY_STATUSES = [Vacancy::STATUS_FILLED, Vacancy::STATUS_CANCELLED];

    public function __construct(protected SelectionService $selections) {}

    /**
     * First history row, written when the application is created (in the caller's transaction).
     */
    public function recordApplied(Application $application, ?User $actor, ?string $remarks = null): void
    {
        $stage = VacancyStage::findOrFail($application->current_vacancy_stage_id);

        $this->history($application, ApplicationStageHistory::ACTION_APPLIED, null, $stage, null, Application::STATUS_ACTIVE, $actor, $remarks);
    }

    /**
     * Move to the next stage (e.g. Applied → Screening, Shortlisted → Interview).
     * Shortlisting has its own action.
     */
    public function advance(Application $application, int $expectedStageId, User $actor, ?string $remarks = null): Application
    {
        return DB::transaction(function () use ($application, $expectedStageId, $actor, $remarks) {
            $application = $this->lockMoving($application, $expectedStageId);
            $this->authorizeAction($actor, 'recruitment.application.move_stage');
            $this->assertVacancyAcceptsProgress($application);

            [$from, $to] = $this->currentAndNext($application);

            if ($to->stage_type === RecruitmentStage::TYPE_SHORTLISTED) {
                throw ValidationException::withMessages(['stage' => ['Use Shortlist to move an application to the shortlist.']]);
            }

            if ($problem = $this->prerequisiteProblem($application, $from, $to)) {
                throw ValidationException::withMessages(['stage' => [$problem]]);
            }

            $application->update(['current_vacancy_stage_id' => $to->id, 'last_activity_at' => now()]);
            $this->history($application, ApplicationStageHistory::ACTION_MOVED, $from, $to, $application->status, $application->status, $actor, $remarks);

            return $application;
        });
    }

    /**
     * Why the application can't leave $from for $to yet (null when it can). Also
     * used read-only by the application page to explain a disabled move.
     */
    public function prerequisiteProblem(Application $application, VacancyStage $from, VacancyStage $to): ?string
    {
        if (in_array($to->stage_type, RecruitmentStage::POST_SHORTLIST_TYPES, true) && $application->status !== Application::STATUS_SHORTLISTED) {
            return "Only shortlisted applications can move to \"{$to->name}\".";
        }

        if ($from->stage_type === RecruitmentStage::TYPE_INTERVIEW
            && ! ApplicationInterview::where('application_id', $application->id)->where('status', ApplicationInterview::STATUS_COMPLETED)->exists()) {
            return "At least one interview must be completed before moving to \"{$to->name}\".";
        }

        if ($from->stage_type === RecruitmentStage::TYPE_ASSESSMENT) {
            $assessments = ApplicationAssessment::where('application_id', $application->id)->pluck('status');

            if ($assessments->intersect(ApplicationAssessment::OPEN)->isNotEmpty()) {
                return "Complete or cancel the open assessments before moving to \"{$to->name}\".";
            }
            if (! $assessments->contains(ApplicationAssessment::STATUS_COMPLETED)) {
                return "At least one assessment must be completed before moving to \"{$to->name}\".";
            }
        }

        return null;
    }

    public function shortlist(Application $application, int $expectedStageId, User $actor, ?string $remarks = null): Application
    {
        return DB::transaction(function () use ($application, $expectedStageId, $actor, $remarks) {
            $application = $this->lockActive($application, $expectedStageId);
            $this->authorizeAction($actor, 'recruitment.shortlist.create');
            $this->assertVacancyAcceptsProgress($application);

            [$from, $to] = $this->currentAndNext($application);

            if ($to->stage_type !== RecruitmentStage::TYPE_SHORTLISTED) {
                throw ValidationException::withMessages(['stage' => ["The application must be in the stage before the shortlist (currently \"{$from->name}\")."]]);
            }

            $screening = ApplicationScreening::where('application_id', $application->id)->first();
            if (! $screening || ! $screening->passed()) {
                throw ValidationException::withMessages(['stage' => ['Only applications that passed screening can be shortlisted.']]);
            }

            $application->update([
                'current_vacancy_stage_id' => $to->id,
                'status' => Application::STATUS_SHORTLISTED,
                'shortlisted_at' => now(),
                'shortlisted_by' => $actor->id,
                'last_activity_at' => now(),
            ]);
            $this->history($application, ApplicationStageHistory::ACTION_SHORTLISTED, $from, $to, Application::STATUS_ACTIVE, Application::STATUS_SHORTLISTED, $actor, $remarks);

            return $application;
        });
    }

    public function reject(Application $application, int $reasonId, User $actor, ?string $remarks = null): Application
    {
        return DB::transaction(function () use ($application, $reasonId, $actor, $remarks) {
            $application = $this->lockNonTerminal($application);
            $this->authorizeAction($actor, 'recruitment.application.reject');
            $this->assertNoOpenOffer($application);

            if (! RejectionReason::whereKey($reasonId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['rejection_reason_id' => ['Choose an active rejection reason.']]);
            }

            $stage = VacancyStage::find($application->current_vacancy_stage_id);
            $fromStatus = $application->status;

            $application->update([
                'status' => Application::STATUS_REJECTED,
                'rejection_reason_id' => $reasonId,
                'rejection_remarks' => $remarks,
                'rejected_at' => now(),
                'rejected_by' => $actor->id,
                'last_activity_at' => now(),
            ]);
            $this->history($application, ApplicationStageHistory::ACTION_REJECTED, $stage, $stage, $fromStatus, Application::STATUS_REJECTED, $actor, $remarks, $reasonId);
            $this->selections->releaseOnClose($application, $actor, 'Opening released: application rejected.');

            return $application;
        });
    }

    public function withdraw(Application $application, string $reason, User $actor): Application
    {
        return DB::transaction(function () use ($application, $reason, $actor) {
            $application = $this->lockNonTerminal($application);
            $this->authorizeAction($actor, 'recruitment.application.withdraw');
            $this->assertNoOpenOffer($application);

            $stage = VacancyStage::find($application->current_vacancy_stage_id);
            $fromStatus = $application->status;

            $application->update([
                'status' => Application::STATUS_WITHDRAWN,
                'withdrawal_reason' => $reason,
                'withdrawn_at' => now(),
                'withdrawn_by' => $actor->id,
                'last_activity_at' => now(),
            ]);
            $this->history($application, ApplicationStageHistory::ACTION_WITHDRAWN, $stage, $stage, $fromStatus, Application::STATUS_WITHDRAWN, $actor, $reason);
            $this->selections->releaseOnClose($application, $actor, 'Opening released: application withdrawn.');

            return $application;
        });
    }

    /**
     * The candidate withdraws their own application from the careers portal.
     * Same transition and history as HR's withdraw (no HR permission involved;
     * the portal checks ownership). Refused once an offer is in play or the
     * candidate has been converted: they contact HR instead.
     */
    public function withdrawByCandidate(Application $application, string $reason): Application
    {
        return DB::transaction(function () use ($application, $reason) {
            $application = $this->lockNonTerminal($application);

            if (JobOffer::where('application_id', $application->id)->whereIn('status', [...JobOffer::OPEN, JobOffer::STATUS_ACCEPTED])->exists()
                || ApplicationConversion::where('application_id', $application->id)->exists()) {
                throw ValidationException::withMessages(['status' => ['This application can no longer be withdrawn online. Please contact our HR team.']]);
            }

            $stage = VacancyStage::find($application->current_vacancy_stage_id);
            $fromStatus = $application->status;
            $remarks = "Withdrawn by the candidate (careers portal): {$reason}";

            $application->update([
                'status' => Application::STATUS_WITHDRAWN,
                'withdrawal_reason' => $remarks,
                'withdrawn_at' => now(),
                'withdrawn_by' => null,
                'last_activity_at' => now(),
            ]);
            $this->history($application, ApplicationStageHistory::ACTION_WITHDRAWN, $stage, $stage, $fromStatus, Application::STATUS_WITHDRAWN, null, $remarks);
            $this->selections->releaseOnClose($application, null, 'Opening released: application withdrawn by the candidate.');

            return $application;
        });
    }

    /**
     * Lock and require an application that is still moving (status "active") and
     * still in the stage the user acted on.
     */
    protected function lockActive(Application $application, int $expectedStageId): Application
    {
        return $this->lockInStatus($application, $expectedStageId, [Application::STATUS_ACTIVE]);
    }

    /**
     * Same, for advance: before the shortlist the status is "active", after it "shortlisted".
     */
    protected function lockMoving(Application $application, int $expectedStageId): Application
    {
        return $this->lockInStatus($application, $expectedStageId, [Application::STATUS_ACTIVE, Application::STATUS_SHORTLISTED]);
    }

    protected function lockInStatus(Application $application, int $expectedStageId, array $statuses): Application
    {
        $application = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();

        if (! in_array($application->status, $statuses, true)) {
            throw ValidationException::withMessages(['stage' => ["This application is {$application->status} and can't move to another stage."]]);
        }

        if ($application->current_vacancy_stage_id !== $expectedStageId) {
            throw ValidationException::withMessages(['stage' => ['This application has already moved to another stage. Refresh to see its current stage.']]);
        }

        return $application;
    }

    protected function lockNonTerminal(Application $application): Application
    {
        $application = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();

        if ($application->isTerminal()) {
            throw ValidationException::withMessages(['status' => ["This application is already {$application->status}."]]);
        }

        return $application;
    }

    protected function assertNoOpenOffer(Application $application): void
    {
        $offer = JobOffer::where('application_id', $application->id)->whereIn('status', JobOffer::OPEN)->first();

        if ($offer) {
            throw ValidationException::withMessages(['status' => ["Offer {$offer->offer_number} is still {$offer->status}. Withdraw the offer first."]]);
        }
    }

    protected function authorizeAction(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw ValidationException::withMessages(['stage' => ['You are not allowed to perform this action.']]);
        }
    }

    public function assertVacancyAcceptsProgress(Application $application): void
    {
        $status = Vacancy::whereKey($application->vacancy_id)->value('status');

        if (in_array($status, self::FROZEN_VACANCY_STATUSES, true)) {
            throw ValidationException::withMessages(['stage' => ["The vacancy is {$status}; applications can only be rejected or withdrawn."]]);
        }
    }

    /**
     * @return array{0: VacancyStage, 1: VacancyStage}
     */
    protected function currentAndNext(Application $application): array
    {
        $from = VacancyStage::findOrFail($application->current_vacancy_stage_id);
        $to = VacancyStage::where('vacancy_id', $from->vacancy_id)
            ->where('sort_order', '>', $from->sort_order)
            ->orderBy('sort_order')
            ->first();

        if (! $to) {
            throw ValidationException::withMessages(['stage' => ["\"{$from->name}\" is the last stage of this vacancy's pipeline."]]);
        }

        return [$from, $to];
    }

    protected function history(Application $application, string $action, ?VacancyStage $from, ?VacancyStage $to, ?string $fromStatus, string $toStatus, ?User $actor, ?string $remarks, ?int $reasonId = null): void
    {
        ApplicationStageHistory::create([
            'application_id' => $application->id,
            'action' => $action,
            'from_vacancy_stage_id' => $from?->id,
            'to_vacancy_stage_id' => $to?->id,
            'from_stage_name' => $from?->name,
            'to_stage_name' => $to?->name,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'rejection_reason_id' => $reasonId,
            'remarks' => $remarks,
            'acted_by' => $actor?->id,
            'acted_at' => now(),
        ]);
    }
}
