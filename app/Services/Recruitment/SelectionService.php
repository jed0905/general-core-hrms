<?php

namespace App\Services\Recruitment;

use App\Models\Application;
use App\Models\ApplicationSelection;
use App\Models\JobOffer;
use App\Models\RecruitmentStage;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Selection decisions. Selection is a human decision taken at the Evaluation
 * stage (not a pipeline stage): it never moves the application, ranks
 * candidates, or creates an employee.
 *
 * A "selected" decision holds one of the vacancy's openings
 * (vacancies.filled_count + 1); a later "not selected" decision, or the
 * application being rejected/withdrawn, releases it. Every decision is a new,
 * immutable application_selections row.
 *
 * Locking: application row, then vacancy row, both before any plain read (so on
 * MySQL/MariaDB the transaction sees what the transaction it waited for wrote).
 * Two selections for the last opening serialize on the vacancy row; the second
 * finds no opening left.
 */
class SelectionService
{
    public function __construct(protected EvaluationService $evaluations) {}

    public function decide(Application $application, string $decision, ?string $remarks, User $actor): ApplicationSelection
    {
        return DB::transaction(function () use ($application, $decision, $remarks, $actor) {
            $application = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $vacancy = Vacancy::whereKey($application->vacancy_id)->lockForUpdate()->firstOrFail();

            if (! $actor->can('recruitment.selection.create')) {
                throw ValidationException::withMessages(['decision' => ['You are not allowed to make selection decisions.']]);
            }
            if ($problem = $this->eligibilityProblem($application)) {
                throw ValidationException::withMessages(['decision' => [$problem]]);
            }

            $current = $this->current($application);
            if ($current?->decision === $decision) {
                throw ValidationException::withMessages(['decision' => [$decision === ApplicationSelection::SELECTED
                    ? 'This candidate is already selected.'
                    : 'This candidate is already marked not selected.']]);
            }

            if ($decision === ApplicationSelection::SELECTED) {
                if (in_array($vacancy->status, [Vacancy::STATUS_DRAFT, Vacancy::STATUS_FILLED, Vacancy::STATUS_CANCELLED], true)) {
                    throw ValidationException::withMessages(['decision' => ["The vacancy is {$vacancy->status}; no one can be selected."]]);
                }
                if ($problem = $this->evaluations->completionProblem($application)) {
                    throw ValidationException::withMessages(['decision' => ["The evaluation isn't complete. {$problem}"]]);
                }
                if ($vacancy->filled_count >= $vacancy->openings) {
                    throw ValidationException::withMessages(['decision' => ["All {$vacancy->openings} opening(s) of this vacancy are already taken by selected candidates."]]);
                }
                $vacancy->update(['filled_count' => $vacancy->filled_count + 1]);
            } elseif ($current?->isSelected()) {
                $this->assertNoBlockingOffer($application);
                $vacancy->update(['filled_count' => max(0, $vacancy->filled_count - 1)]);
            }

            $selection = $this->record($application, $decision, $remarks, $actor, false);
            $application->update(['last_activity_at' => now()]);

            return $selection;
        });
    }

    /**
     * Called by ApplicationPipelineService inside its reject/withdraw transaction
     * (application already locked): release the opening a selected candidate held.
     */
    public function releaseOnClose(Application $application, ?User $actor, string $reason): void
    {
        if (! $this->current($application)?->isSelected()) {
            return;
        }

        $vacancy = Vacancy::whereKey($application->vacancy_id)->lockForUpdate()->firstOrFail();
        $vacancy->update(['filled_count' => max(0, $vacancy->filled_count - 1)]);
        $this->record($application, ApplicationSelection::NOT_SELECTED, $reason, $actor, true);
    }

    /**
     * Why the application can't receive a selection decision (null when it can).
     */
    public function eligibilityProblem(Application $application): ?string
    {
        if ($application->status !== Application::STATUS_SHORTLISTED) {
            return $application->isTerminal()
                ? "This application is {$application->status}."
                : 'Only shortlisted applications in the Evaluation stage can be selected.';
        }

        $stage = VacancyStage::find($application->current_vacancy_stage_id);
        if ($stage?->stage_type !== RecruitmentStage::TYPE_EVALUATION) {
            return 'Move the application to the Evaluation stage before a selection decision.';
        }

        return null;
    }

    /**
     * Openings and current decisions for the vacancy workspace (no ranking).
     *
     * @return array{openings: int, filled: int, remaining: int, in_evaluation: int, selected: int, not_selected: int}
     */
    public function vacancySummary(Vacancy $vacancy): array
    {
        $current = ApplicationSelection::whereIn('id', ApplicationSelection::where('vacancy_id', $vacancy->id)
            ->selectRaw('max(id)')->groupBy('application_id'))
            ->pluck('decision');

        return [
            'openings' => (int) $vacancy->openings,
            'filled' => (int) $vacancy->filled_count,
            'remaining' => $vacancy->remaining_openings,
            'in_evaluation' => Application::where('vacancy_id', $vacancy->id)
                ->whereIn('status', [Application::STATUS_ACTIVE, Application::STATUS_SHORTLISTED])
                ->whereHas('currentStage', fn ($q) => $q->where('stage_type', RecruitmentStage::TYPE_EVALUATION))
                ->count(),
            'selected' => $current->filter(fn ($d) => $d === ApplicationSelection::SELECTED)->count(),
            'not_selected' => $current->filter(fn ($d) => $d === ApplicationSelection::NOT_SELECTED)->count(),
        ];
    }

    public function current(Application $application): ?ApplicationSelection
    {
        return ApplicationSelection::where('application_id', $application->id)->orderByDesc('id')->first();
    }

    /**
     * A selected candidate keeps the opening while an offer is in play or accepted.
     */
    public function assertNoBlockingOffer(Application $application): void
    {
        $offer = JobOffer::where('application_id', $application->id)->whereIn('status', JobOffer::ACTIVE)->first();

        if ($offer) {
            throw ValidationException::withMessages(['decision' => [$offer->status === JobOffer::STATUS_ACCEPTED
                ? "The candidate accepted offer {$offer->offer_number}; the selection can no longer be reversed here."
                : "Offer {$offer->offer_number} is still {$offer->status}. Withdraw it first."]]);
        }
    }

    protected function record(Application $application, string $decision, ?string $remarks, ?User $actor, bool $automatic): ApplicationSelection
    {
        return ApplicationSelection::create([
            'application_id' => $application->id,
            'vacancy_id' => $application->vacancy_id,
            'applicant_id' => $application->applicant_id,
            'decision' => $decision,
            'is_automatic' => $automatic,
            'remarks' => $remarks,
            'decided_by' => $actor?->id,
            'decided_at' => now(),
        ] + $this->evaluations->summary($application));
    }
}
