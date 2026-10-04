<?php

namespace App\Services\Recruitment;

use App\Models\Application;
use App\Models\ApplicationEvaluation;
use App\Models\ApplicationEvaluationScore;
use App\Models\ApplicationInterview;
use App\Models\EvaluationCriterion;
use App\Models\InterviewPanelist;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Interview scorecards. Only an assigned panelist writes, and only their own
 * scorecard, once the interview is completed. Drafts can be saved any number of
 * times; submitting locks the scorecard for good. A recommendation is advice
 * only: nothing here selects, rejects or moves the application.
 *
 * One scorecard per application + interview + evaluator: the interview row is
 * locked so simultaneous saves serialize, and the unique index is the backstop.
 */
class EvaluationService
{
    /**
     * @param  array{recommendation?: ?string, comments?: ?string, scores?: array<int, array{evaluation_criterion_id: int, rating: int, comments?: ?string}>}  $data
     */
    public function saveDraft(ApplicationInterview $interview, array $data, User $actor): ApplicationEvaluation
    {
        return $this->save($interview, $data, $actor, submit: false);
    }

    public function submit(ApplicationInterview $interview, array $data, User $actor): ApplicationEvaluation
    {
        return $this->save($interview, $data, $actor, submit: true);
    }

    /**
     * The actor's own scorecard for an interview, if any.
     */
    public function ownScorecard(ApplicationInterview $interview, User $user): ?ApplicationEvaluation
    {
        return $user->employee_id
            ? $interview->evaluations()->where('evaluator_employee_id', $user->employee_id)->with('scores')->first()
            : null;
    }

    /**
     * Why the application's evaluation isn't complete yet (null when it is): at
     * least one completed interview, and a submitted scorecard from every
     * panelist of every completed interview. Used before a selection decision.
     */
    public function completionProblem(Application $application): ?string
    {
        $interviews = ApplicationInterview::where('application_id', $application->id)
            ->where('status', ApplicationInterview::STATUS_COMPLETED)
            ->with('panelists.employee:id,emp_first_name,emp_last_name')
            ->get();

        if ($interviews->isEmpty()) {
            return 'The application has no completed interview.';
        }

        $submitted = ApplicationEvaluation::where('application_id', $application->id)
            ->where('status', ApplicationEvaluation::STATUS_SUBMITTED)
            ->get(['application_interview_id', 'evaluator_employee_id'])
            ->map(fn ($e) => "{$e->application_interview_id}:{$e->evaluator_employee_id}");

        $missing = $interviews->flatMap(fn ($interview) => $interview->panelists
            ->reject(fn ($p) => $submitted->contains("{$interview->id}:{$p->employee_id}"))
            ->map(fn ($p) => "{$p->employee->emp_first_name} {$p->employee->emp_last_name} (round {$interview->round})"));

        return $missing->isEmpty() ? null : 'Scorecards still missing from: '.$missing->implode(', ').'.';
    }

    /**
     * Facts about the submitted scorecards (no ranking): counts per recommendation and the average rating.
     *
     * @return array{completed_interviews: int, submitted_evaluations: int, recommend_count: int, neutral_count: int, do_not_recommend_count: int, average_rating: ?float}
     */
    public function summary(Application $application): array
    {
        $evaluations = ApplicationEvaluation::where('application_id', $application->id)
            ->where('status', ApplicationEvaluation::STATUS_SUBMITTED)
            ->pluck('recommendation', 'id');

        $average = $evaluations->isEmpty() ? null
            : ApplicationEvaluationScore::whereIn('application_evaluation_id', $evaluations->keys())->avg('rating');

        return [
            'completed_interviews' => ApplicationInterview::where('application_id', $application->id)->where('status', ApplicationInterview::STATUS_COMPLETED)->count(),
            'submitted_evaluations' => $evaluations->count(),
            'recommend_count' => $evaluations->filter(fn ($r) => $r === ApplicationEvaluation::RECOMMEND)->count(),
            'neutral_count' => $evaluations->filter(fn ($r) => $r === ApplicationEvaluation::NEUTRAL)->count(),
            'do_not_recommend_count' => $evaluations->filter(fn ($r) => $r === ApplicationEvaluation::DO_NOT_RECOMMEND)->count(),
            'average_rating' => $average === null ? null : round((float) $average, 2),
        ];
    }

    protected function save(ApplicationInterview $interview, array $data, User $actor, bool $submit): ApplicationEvaluation
    {
        try {
            return DB::transaction(function () use ($interview, $data, $actor, $submit) {
                $application = Application::whereKey($interview->application_id)->lockForUpdate()->firstOrFail();
                $interview = ApplicationInterview::whereKey($interview->id)->lockForUpdate()->firstOrFail();

                $this->assertCanEvaluate($application, $interview, $actor);

                $evaluation = ApplicationEvaluation::where('application_id', $application->id)
                    ->where('application_interview_id', $interview->id)
                    ->where('evaluator_employee_id', $actor->employee_id)
                    ->lockForUpdate()
                    ->first();

                if ($evaluation?->isSubmitted()) {
                    throw ValidationException::withMessages(['evaluation' => ['Your scorecard has already been submitted and can no longer change.']]);
                }

                $scores = $this->validScores($data['scores'] ?? [], $evaluation);

                if ($submit) {
                    $this->assertComplete($scores, $data);
                }

                $attributes = [
                    'recommendation' => $data['recommendation'] ?? null,
                    'comments' => $data['comments'] ?? null,
                ];

                $evaluation ??= ApplicationEvaluation::create([
                    'application_id' => $application->id,
                    'application_interview_id' => $interview->id,
                    'evaluator_employee_id' => $actor->employee_id,
                    'evaluator_user_id' => $actor->id,
                    'status' => ApplicationEvaluation::STATUS_DRAFT,
                ] + $attributes);

                $this->syncScores($evaluation, $scores);

                $evaluation->update($attributes + ($submit
                    ? ['status' => ApplicationEvaluation::STATUS_SUBMITTED, 'submitted_at' => now()]
                    : []));

                return $evaluation->load('scores');
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'application_evaluations_unique') || str_contains($e->getMessage(), 'application_evaluations.application_id')) {
                throw ValidationException::withMessages(['evaluation' => ['Your scorecard was saved from another window. Refresh and try again.']]);
            }
            throw $e;
        }
    }

    protected function assertCanEvaluate(Application $application, ApplicationInterview $interview, User $actor): void
    {
        $assigned = $actor->employee_id !== null && InterviewPanelist::where('application_interview_id', $interview->id)
            ->where('employee_id', $actor->employee_id)->exists();

        if (! $assigned) {
            throw ValidationException::withMessages(['evaluation' => ['Only the interview\'s panelists can submit a scorecard for it.']]);
        }
        if ($interview->status !== ApplicationInterview::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['evaluation' => ['Scorecards can be written once the interview is marked completed.']]);
        }
        if ($application->isTerminal()) {
            throw ValidationException::withMessages(['evaluation' => ["This application is {$application->status}; scorecards are closed."]]);
        }
    }

    /**
     * Ratings must be 1–5 for an active criterion (or one already on this draft).
     *
     * @return array<int, array{rating: int, comments: ?string, name: string}> keyed by criterion id
     */
    protected function validScores(array $input, ?ApplicationEvaluation $evaluation): array
    {
        $existing = $evaluation ? $evaluation->scores()->pluck('evaluation_criterion_id')->all() : [];
        $criteria = EvaluationCriterion::where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $existing))->pluck('name', 'id');

        $scores = [];
        foreach ($input as $row) {
            $id = (int) ($row['evaluation_criterion_id'] ?? 0);
            $rating = (int) ($row['rating'] ?? 0);

            if (! isset($criteria[$id])) {
                throw ValidationException::withMessages(['scores' => ['One of the criteria is no longer available. Refresh the page.']]);
            }
            if ($rating < 1 || $rating > 5) {
                throw ValidationException::withMessages(['scores' => ['Ratings must be between 1 and 5.']]);
            }
            if (isset($scores[$id])) {
                throw ValidationException::withMessages(['scores' => ['Each criterion can be rated once.']]);
            }

            $scores[$id] = ['rating' => $rating, 'comments' => $row['comments'] ?? null, 'name' => $criteria[$id]];
        }

        return $scores;
    }

    protected function assertComplete(array $scores, array $data): void
    {
        $missing = EvaluationCriterion::active()->whereNotIn('id', array_keys($scores))->pluck('name');

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['scores' => ['Rate every criterion before submitting: '.$missing->implode(', ').'.']]);
        }
        if (! in_array($data['recommendation'] ?? null, ApplicationEvaluation::RECOMMENDATIONS, true)) {
            throw ValidationException::withMessages(['recommendation' => ['Choose a recommendation before submitting.']]);
        }
    }

    protected function syncScores(ApplicationEvaluation $evaluation, array $scores): void
    {
        ApplicationEvaluationScore::where('application_evaluation_id', $evaluation->id)
            ->whereNotIn('evaluation_criterion_id', array_keys($scores))
            ->get()
            ->each->delete();

        foreach ($scores as $criterionId => $score) {
            ApplicationEvaluationScore::updateOrCreate(
                ['application_evaluation_id' => $evaluation->id, 'evaluation_criterion_id' => $criterionId],
                ['criterion_name' => $score['name'], 'rating' => $score['rating'], 'comments' => $score['comments']]
            );
        }
    }
}
