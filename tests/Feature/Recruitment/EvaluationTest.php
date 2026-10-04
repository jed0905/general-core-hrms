<?php

namespace Tests\Feature\Recruitment;

use App\Models\ApplicationEvaluation;
use App\Models\ApplicationEvaluationScore;
use App\Models\EvaluationCriterion;
use App\Models\User;
use App\Services\Recruitment\ApplicationPipelineService;
use App\Services\Recruitment\EvaluationService;
use App\Services\Recruitment\InterviewService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Interview scorecards: panelist-only drafts, submission, immutability,
 * ownership, rating validation, one scorecard per evaluator, and who can read them.
 */
class EvaluationTest extends RecruitmentTestCase
{
    private User $eve;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eve = $this->userWithRole('employee', ['emp_first_name' => 'Eve', 'emp_last_name' => 'Engineer']);
    }

    private function save(User $as, $interview, array $data, bool $submit = false)
    {
        return $submit
            ? $this->actingAs($as)->post(route('recruitment.interviews.evaluation.submit', $interview), $data)
            : $this->actingAs($as)->put(route('recruitment.interviews.evaluation.save', $interview), $data);
    }

    #[Test]
    public function panelists_save_drafts_then_submit_their_own_scorecards(): void
    {
        $app = $this->inInterview($this->openVacancy());
        $interview = $this->completedInterview($app, [$this->eve, $this->maya, $this->dina]);
        $criteria = EvaluationCriterion::active()->get();

        // A partial draft, saved twice.
        $this->save($this->eve, $interview, ['scores' => [['evaluation_criterion_id' => $criteria[0]->id, 'rating' => 3]]])->assertSessionHasNoErrors();
        $this->save($this->eve, $interview, ['comments' => 'Halfway', 'recommendation' => 'neutral', 'scores' => [
            ['evaluation_criterion_id' => $criteria[0]->id, 'rating' => 4, 'comments' => 'Knows Laravel'],
            ['evaluation_criterion_id' => $criteria[1]->id, 'rating' => 2],
        ]])->assertSessionHasNoErrors();

        $draft = ApplicationEvaluation::sole();
        $this->assertSame(['draft', $this->eve->employee_id, $this->eve->id, $interview->id, $app->id, 'neutral', 'Halfway', null],
            [$draft->status, $draft->evaluator_employee_id, $draft->evaluator_user_id, $draft->application_interview_id, $draft->application_id, $draft->recommendation, $draft->comments, $draft->submitted_at]);
        $this->assertSame([[$criteria[0]->id, 4, 'Knows Laravel', $criteria[0]->name], [$criteria[1]->id, 2, null, $criteria[1]->name]],
            $draft->scores->map(fn ($s) => [$s->evaluation_criterion_id, $s->rating, $s->comments, $s->criterion_name])->all());

        // Submitting needs every active criterion and a recommendation.
        $this->save($this->eve, $interview, ['recommendation' => 'recommend', 'scores' => [['evaluation_criterion_id' => $criteria[0]->id, 'rating' => 4]]], true)->assertSessionHasErrors('scores');
        $this->save($this->eve, $interview, ['scores' => $this->scores()], true)->assertSessionHasErrors('recommendation');
        $this->assertSame('draft', $draft->fresh()->status);

        $this->save($this->eve, $interview, ['recommendation' => 'recommend', 'comments' => 'Hire-worthy', 'scores' => $this->scores(5)], true)->assertSessionHasNoErrors();
        $draft->refresh();
        $this->assertSame(['submitted', 'recommend', 'Hire-worthy', '2026-10-05 12:00:00', 6], [$draft->status, $draft->recommendation, $draft->comments, $draft->submitted_at->format('Y-m-d H:i:s'), $draft->scores()->count()]);

        // A second panelist's scorecard is separate.
        $this->save($this->maya, $interview, ['recommendation' => 'do_not_recommend', 'scores' => $this->scores(2)], true)->assertSessionHasNoErrors();
        $this->assertSame(2, ApplicationEvaluation::where('status', 'submitted')->count());
        $this->assertSame(['panelists' => 3, 'submitted' => 2, 'drafts' => 0], app(InterviewService::class)->evaluationProgress($interview));

        // Recommendations are advice only: the application doesn't move or change status.
        $app->refresh();
        $this->assertSame(['shortlisted', $this->stage($app->vacancy, 'interview')->id], [$app->status, $app->current_vacancy_stage_id]);
    }

    #[Test]
    public function submitted_scorecards_are_immutable(): void
    {
        $interview = $this->completedInterview($this->inInterview($this->openVacancy()), [$this->eve]);
        $this->save($this->eve, $interview, ['recommendation' => 'recommend', 'comments' => 'Final', 'scores' => $this->scores(4)], true)->assertSessionHasNoErrors();
        $evaluation = ApplicationEvaluation::sole();
        $submittedAt = $evaluation->submitted_at;

        Carbon::setTestNow('2026-10-06 09:00:00');
        $this->save($this->eve, $interview, ['recommendation' => 'do_not_recommend', 'scores' => $this->scores(1)])->assertSessionHasErrors('evaluation');
        $this->save($this->eve, $interview, ['recommendation' => 'do_not_recommend', 'scores' => $this->scores(1)], true)->assertSessionHasErrors('evaluation');

        $evaluation->refresh();
        $this->assertSame(['recommend', 'Final', [4], $this->eve->employee_id, $interview->id], [$evaluation->recommendation, $evaluation->comments, $evaluation->scores->pluck('rating')->unique()->values()->all(), $evaluation->evaluator_employee_id, $evaluation->application_interview_id]);
        $this->assertTrue($submittedAt->equalTo($evaluation->submitted_at));

        // Even direct model writes are refused.
        foreach ([
            fn () => $evaluation->update(['recommendation' => 'neutral']),
            fn () => $evaluation->update(['evaluator_employee_id' => $this->maya->employee_id]),
            fn () => $evaluation->update(['application_interview_id' => 999]),
            fn () => $evaluation->update(['submitted_at' => now()]),
            fn () => $evaluation->delete(),
            fn () => $evaluation->scores->first()->update(['rating' => 1]),
            fn () => $evaluation->scores->first()->delete(),
            fn () => ApplicationEvaluationScore::create(['application_evaluation_id' => $evaluation->id, 'evaluation_criterion_id' => EvaluationCriterion::first()->id, 'criterion_name' => 'x', 'rating' => 1]),
        ] as $write) {
            try {
                $write();
                $this->fail('a submitted scorecard changed');
            } catch (LogicException) {
            }
        }
        $this->assertSame(6, ApplicationEvaluationScore::where('application_evaluation_id', $evaluation->id)->where('rating', 4)->count());
    }

    #[Test]
    public function only_assigned_panelists_of_a_completed_interview_write_scorecards(): void
    {
        $vacancy = $this->openVacancy(['hiring_manager_id' => $this->ramon->employee_id]);
        $app = $this->inInterview($vacancy);
        $scheduled = $this->scheduleInterview($app, [$this->eve], ['scheduled_date' => '2026-10-09']);
        $completed = $this->completedInterview($app, [$this->maya]);
        $payload = ['recommendation' => 'recommend', 'scores' => $this->scores()];

        $this->save($this->eve, $scheduled, $payload)->assertForbidden(); // interview not completed yet
        foreach ([$this->eve, $this->hrStaff, $this->dina, $this->ramon, $this->outsider] as $notOnPanel) {
            $this->save($notOnPanel, $completed, $payload)->assertForbidden();
            $this->save($notOnPanel, $completed, $payload, true)->assertForbidden();
        }
        $this->assertSame(0, ApplicationEvaluation::count());

        // Service-level guard as well.
        $this->expectException(ValidationException::class);
        app(EvaluationService::class)->saveDraft($completed, $payload, $this->eve);
    }

    #[Test]
    public function one_evaluator_cannot_touch_anothers_scorecard(): void
    {
        $interview = $this->completedInterview($this->inInterview($this->openVacancy()), [$this->eve, $this->maya]);
        $this->save($this->eve, $interview, ['comments' => 'Eve draft', 'scores' => [['evaluation_criterion_id' => EvaluationCriterion::first()->id, 'rating' => 5]]])->assertSessionHasNoErrors();
        $this->save($this->maya, $interview, ['comments' => 'Maya draft'])->assertSessionHasNoErrors();

        $eves = ApplicationEvaluation::where('evaluator_employee_id', $this->eve->employee_id)->sole();
        $mayas = ApplicationEvaluation::where('evaluator_employee_id', $this->maya->employee_id)->sole();
        $this->assertSame(['Eve draft', 'Maya draft', 1, 0], [$eves->comments, $mayas->comments, $eves->scores()->count(), $mayas->scores()->count()]);

        // Policy: each owns only their own draft; nobody may delete.
        $this->assertTrue($this->eve->can('update', $eves));
        $this->assertFalse($this->eve->can('update', $mayas));
        $this->assertFalse($this->eve->can('view', $mayas), 'drafts are private to their owner');
        $this->assertFalse($this->dina->can('view', $mayas), 'HR sees submitted scorecards only');
        $this->assertFalse($this->eve->can('delete', $eves));

        // Maya's page shows only her own scorecard.
        $this->actingAs($this->maya)->get(route('recruitment.interviews.show', $interview))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('myScorecard.id', $mayas->id)->where('scorecards', [])->where('can.evaluate', true));
    }

    #[Test]
    public function ratings_and_recommendations_are_validated(): void
    {
        $interview = $this->completedInterview($this->inInterview($this->openVacancy()), [$this->eve]);
        $id = EvaluationCriterion::first()->id;

        foreach ([0, 6, 'great', 3.5, null] as $bad) {
            $this->save($this->eve, $interview, ['scores' => [['evaluation_criterion_id' => $id, 'rating' => $bad]]])->assertSessionHasErrors('scores.0.rating');
        }
        $this->save($this->eve, $interview, ['scores' => [['evaluation_criterion_id' => 99999, 'rating' => 3]]])->assertSessionHasErrors('scores.0.evaluation_criterion_id');
        $this->save($this->eve, $interview, ['scores' => [['evaluation_criterion_id' => $id, 'rating' => 3], ['evaluation_criterion_id' => $id, 'rating' => 4]]])->assertSessionHasErrors('scores.0.evaluation_criterion_id');
        $this->save($this->eve, $interview, ['recommendation' => 'hire_now'])->assertSessionHasErrors('recommendation');

        // An inactive criterion can't be newly rated.
        $inactive = EvaluationCriterion::where('code', 'leadership')->first();
        $inactive->update(['is_active' => false]);
        $this->save($this->eve, $interview, ['scores' => [['evaluation_criterion_id' => $inactive->id, 'rating' => 3]]])->assertSessionHasErrors('scores');
        // Submitting no longer requires it.
        $this->save($this->eve, $interview, ['recommendation' => 'neutral', 'scores' => $this->scores(3)], true)->assertSessionHasNoErrors();
        $this->assertSame(5, ApplicationEvaluation::sole()->scores()->count());

        // Rating out of range is refused by the service too (not just the request).
        $other = $this->completedInterview($this->inInterview($this->openVacancy()), [$this->eve], ['scheduled_date' => '2026-10-09']);
        $this->expectException(ValidationException::class);
        app(EvaluationService::class)->saveDraft($other, ['scores' => [['evaluation_criterion_id' => $id, 'rating' => 9]]], $this->eve);
    }

    #[Test]
    public function the_database_allows_one_scorecard_per_application_interview_and_evaluator(): void
    {
        $interview = $this->completedInterview($this->inInterview($this->openVacancy()), [$this->eve]);
        $this->save($this->eve, $interview, ['comments' => 'one'])->assertSessionHasNoErrors();
        $this->save($this->eve, $interview, ['comments' => 'same scorecard'])->assertSessionHasNoErrors();
        $this->assertSame(1, ApplicationEvaluation::count());

        $this->expectException(UniqueConstraintViolationException::class);
        ApplicationEvaluation::create([
            'application_id' => $interview->application_id,
            'application_interview_id' => $interview->id,
            'evaluator_employee_id' => $this->eve->employee_id,
            'status' => 'draft',
        ]);
    }

    #[Test]
    public function renaming_a_criterion_never_changes_a_submitted_scorecard(): void
    {
        $interview = $this->completedInterview($this->inInterview($this->openVacancy()), [$this->eve]);
        $this->save($this->eve, $interview, ['recommendation' => 'recommend', 'scores' => $this->scores()], true)->assertSessionHasNoErrors();

        $criterion = EvaluationCriterion::where('code', 'communication')->first();
        $this->actingAs($this->dina)->put(route('recruitment.settings.criteria.update', $criterion), ['name' => 'Verbal skills'])->assertSessionHasNoErrors();

        $this->assertSame('Communication', ApplicationEvaluationScore::where('evaluation_criterion_id', $criterion->id)->value('criterion_name'));
    }

    #[Test]
    public function submitted_scorecards_are_visible_to_hr_and_the_hiring_manager_only(): void
    {
        $vacancy = $this->openVacancy(['hiring_manager_id' => $this->ramon->employee_id]);
        $app = $this->inInterview($vacancy);
        $interview = $this->completedInterview($app, [$this->eve, $this->maya]);
        $this->save($this->eve, $interview, ['recommendation' => 'recommend', 'scores' => $this->scores()], true);
        $this->save($this->maya, $interview, ['comments' => 'still drafting']);

        foreach ([$this->hrStaff, $this->ramon] as $viewer) {
            $this->actingAs($viewer)->get(route('recruitment.interviews.show', $interview))->assertOk()
                ->assertInertia(fn (AssertableInertia $p) => $p->has('scorecards', 1)->where('scorecards.0.recommendation', 'recommend')
                    ->where('progress', ['panelists' => 2, 'submitted' => 1, 'drafts' => 1]));
            $this->actingAs($viewer)->get(route('recruitment.applications.show', $app))->assertOk()
                ->assertInertia(fn (AssertableInertia $p) => $p->has('evaluations', 1)->where('interviews.0.submitted_evaluations_count', 1));
        }

        // A panelist sees their own scorecard, never a colleague's.
        $this->actingAs($this->maya)->get(route('recruitment.interviews.show', $interview))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('scorecards', [])->where('myScorecard.comments', 'still drafting'));
    }

    #[Test]
    public function scorecards_close_when_the_application_is_rejected(): void
    {
        $app = $this->inInterview($this->openVacancy());
        $interview = $this->completedInterview($app, [$this->eve]);
        $this->save($this->eve, $interview, ['comments' => 'draft'])->assertSessionHasNoErrors();
        app(ApplicationPipelineService::class)->reject($app, $this->reason()->id, $this->hrStaff);

        $this->save($this->eve, $interview, ['recommendation' => 'recommend', 'scores' => $this->scores()], true)->assertSessionHasErrors('evaluation');
        $this->assertSame('draft', ApplicationEvaluation::sole()->status);
    }
}
