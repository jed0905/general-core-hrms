<?php

namespace Tests\Feature\Recruitment;

use App\Models\ApplicationSelection;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Recruitment\ApplicationPipelineService;
use App\Services\Recruitment\AssessmentService;
use App\Services\Recruitment\EvaluationService;
use App\Services\Recruitment\OfferService;
use App\Services\Recruitment\SelectionService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Selection decisions at the Evaluation stage: eligibility, evaluation
 * completeness, openings, history, revisions and access.
 */
class SelectionTest extends RecruitmentTestCase
{
    private function decide(User $as, $application, string $decision = 'selected', ?string $remarks = 'Panel recommends.')
    {
        return $this->actingAs($as)->post(route('recruitment.applications.selection.store', $application), ['decision' => $decision, 'remarks' => $remarks]);
    }

    #[Test]
    public function an_evaluated_candidate_is_selected_with_an_auditable_record(): void
    {
        $vacancy = $this->openVacancy(['openings' => 2]);
        $app = $this->evaluated($vacancy);
        $employees = Employee::count();

        $this->decide($this->dina, $app)->assertSessionHasNoErrors();

        $selection = ApplicationSelection::sole();
        $this->assertSame(
            ['selected', $app->id, $vacancy->id, $app->applicant_id, $this->dina->id, 'Panel recommends.', false, 1, 1, 1, 0, 0, '4.00'],
            [$selection->decision, $selection->application_id, $selection->vacancy_id, $selection->applicant_id, $selection->decided_by, $selection->remarks, $selection->is_automatic,
                $selection->completed_interviews, $selection->submitted_evaluations, $selection->recommend_count, $selection->neutral_count, $selection->do_not_recommend_count, $selection->average_rating]
        );
        $this->assertNotNull($selection->decided_at);
        $this->assertSame(1, $vacancy->fresh()->filled_count);

        // Selection is a decision, not a stage or a status; nothing is hired.
        $app->refresh();
        $this->assertSame(['shortlisted', $this->stage($vacancy, 'evaluation')->id], [$app->status, $app->current_vacancy_stage_id]);
        $this->assertSame($employees, Employee::count());
        $this->assertSame(6, $app->history()->count(), 'no stage history written for a selection');
    }

    #[Test]
    public function selection_needs_the_evaluation_stage_and_a_live_application(): void
    {
        $vacancy = $this->openVacancy(['openings' => 5]);

        foreach (['applied' => $this->applyTo($vacancy), 'screening' => $this->inScreening($vacancy, 'passed'), 'shortlisted' => $this->shortlisted($vacancy), 'interview' => $this->inInterview($vacancy), 'assessment' => $this->inAssessment($vacancy)] as $stage => $app) {
            $response = $this->decide($this->dina, $app);
            in_array($stage, ['applied', 'screening'], true) ? $response->assertForbidden() : $response->assertSessionHasErrors('decision');
        }

        $rejected = $this->evaluated($vacancy);
        app(ApplicationPipelineService::class)->reject($rejected, $this->reason()->id, $this->hrStaff);
        $this->decide($this->dina, $rejected)->assertForbidden();

        $withdrawn = $this->evaluated($vacancy);
        app(ApplicationPipelineService::class)->withdraw($withdrawn, 'Took another job', $this->hrStaff);
        $this->decide($this->dina, $withdrawn)->assertForbidden();

        $this->assertSame(0, ApplicationSelection::count());
        $this->assertSame(0, $vacancy->fresh()->filled_count);
    }

    #[Test]
    public function selection_needs_every_panel_scorecard_submitted(): void
    {
        $vacancy = $this->openVacancy();
        $app = $this->inInterview($vacancy);
        $interview = $this->completedInterview($app, [$this->maya, $this->outsider]);
        app(EvaluationService::class)->submit($interview, ['recommendation' => 'recommend', 'scores' => $this->scores()], $this->maya);
        app(EvaluationService::class)->saveDraft($interview, ['comments' => 'drafting'], $this->outsider);
        $app = $this->moveOn($app);
        $assessment = app(AssessmentService::class)->create($app, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);
        app(AssessmentService::class)->complete($assessment, ['score' => 1, 'maximum_score' => 2], $this->hrStaff);
        $app = $this->moveOn($app);

        $this->decide($this->dina, $app)->assertSessionHasErrors(['decision' => "The evaluation isn't complete. Scorecards still missing from: Otto Outsider (round 1)."]);

        // A not-selected decision doesn't need a complete evaluation.
        $this->decide($this->dina, $app, 'not_selected', 'Position requirements changed.')->assertSessionHasNoErrors();
        $this->assertSame('not_selected', ApplicationSelection::sole()->decision);
    }

    #[Test]
    public function openings_are_respected_and_the_vacancy_never_overfills(): void
    {
        $vacancy = $this->openVacancy(['openings' => 2]);
        [$a, $b, $c] = [$this->evaluated($vacancy), $this->evaluated($vacancy), $this->evaluated($vacancy)];

        $this->decide($this->dina, $a)->assertSessionHasNoErrors();
        $this->decide($this->dina, $b)->assertSessionHasNoErrors();
        $this->decide($this->dina, $c)->assertSessionHasErrors(['decision' => 'All 2 opening(s) of this vacancy are already taken by selected candidates.']);
        $this->assertSame([2, 2, 0], [$vacancy->fresh()->openings, $vacancy->fresh()->filled_count, $vacancy->fresh()->remaining_openings]);

        // Releasing one (a later "not selected" decision) frees the opening for another candidate.
        $this->decide($this->dina, $b, 'not_selected', 'Salary expectations too high.')->assertSessionHasNoErrors();
        $this->assertSame(1, $vacancy->fresh()->filled_count);
        $this->decide($this->dina, $c)->assertSessionHasNoErrors();
        $this->assertSame(2, $vacancy->fresh()->filled_count);

        $current = fn ($app) => app(SelectionService::class)->current($app)->decision;
        $this->assertSame(['selected', 'not_selected', 'selected'], [$current($a), $current($b), $current($c)]);
        $this->assertSame(['openings' => 2, 'filled' => 2, 'remaining' => 0, 'in_evaluation' => 3, 'selected' => 2, 'not_selected' => 1], app(SelectionService::class)->vacancySummary($vacancy->fresh()));
    }

    #[Test]
    public function vacancies_are_isolated_and_frozen_vacancies_cannot_select(): void
    {
        $one = $this->openVacancy(['openings' => 1]);
        $two = $this->openVacancy(['openings' => 1]);
        $this->decide($this->dina, $this->evaluated($one))->assertSessionHasNoErrors();
        $this->decide($this->dina, $this->evaluated($two))->assertSessionHasNoErrors();
        $this->assertSame([1, 1], [$one->fresh()->filled_count, $two->fresh()->filled_count]);

        $cancelled = $this->openVacancy();
        $app = $this->evaluated($cancelled);
        Vacancy::whereKey($cancelled->id)->update(['status' => Vacancy::STATUS_CANCELLED]);
        $this->decide($this->dina, $app)->assertSessionHasErrors('decision');
    }

    #[Test]
    public function decisions_are_history_and_revisions_are_new_rows(): void
    {
        $vacancy = $this->openVacancy();
        $app = $this->evaluated($vacancy);

        $this->decide($this->dina, $app)->assertSessionHasNoErrors();
        $this->decide($this->dina, $app)->assertSessionHasErrors(['decision' => 'This candidate is already selected.']);
        $this->decide($this->dina, $app, 'not_selected', null)->assertSessionHasErrors('remarks');
        $this->decide($this->dina, $app, 'not_selected', 'Changed our mind.')->assertSessionHasNoErrors();
        $this->decide($this->dina, $app)->assertSessionHasNoErrors();

        $this->assertSame(['selected', 'not_selected', 'selected'], $app->selections->pluck('decision')->all());
        $this->assertSame(1, $vacancy->fresh()->filled_count);

        $first = $app->selections->first();
        try {
            $first->update(['decision' => 'not_selected']);
            $this->fail('selection history changed');
        } catch (LogicException) {
        }
        $this->expectException(LogicException::class);
        $first->delete();
    }

    #[Test]
    public function a_selection_with_an_offer_in_play_cannot_be_reversed(): void
    {
        $vacancy = $this->openVacancy();
        $app = $this->evaluated($vacancy);
        $this->select($app);
        $offer = $this->draftOffer($app);

        $this->decide($this->dina, $app, 'not_selected', 'x')->assertSessionHasErrors(['decision' => "Offer {$offer->offer_number} is still draft. Withdraw it first."]);
        app(OfferService::class)->withdraw($offer, 'Re-thinking', $this->hrStaff);
        $this->decide($this->dina, $app, 'not_selected', 'x')->assertSessionHasNoErrors();
        $this->assertSame(0, $vacancy->fresh()->filled_count);
    }

    #[Test]
    public function rejecting_or_withdrawing_a_selected_application_releases_its_opening(): void
    {
        $vacancy = $this->openVacancy(['openings' => 2]);
        $a = $this->evaluated($vacancy);
        $b = $this->evaluated($vacancy);
        $this->select($a);
        $this->select($b);
        $offer = $this->draftOffer($b);

        // Not while an offer is in play.
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.withdraw', $b), ['reason' => 'Moved abroad'])->assertSessionHasErrors('status');

        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.reject', $a), ['rejection_reason_id' => $this->reason()->id])->assertSessionHasNoErrors();
        app(OfferService::class)->withdraw($offer, 'Candidate withdrew', $this->hrStaff);
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.withdraw', $b), ['reason' => 'Moved abroad'])->assertSessionHasNoErrors();

        $this->assertSame(0, $vacancy->fresh()->filled_count);
        $release = app(SelectionService::class)->current($a);
        $this->assertSame(['not_selected', true, 'Opening released: application rejected.', $this->hrStaff->id], [$release->decision, $release->is_automatic, $release->remarks, $release->decided_by]);
    }

    #[Test]
    public function only_hr_management_decides_and_hiring_managers_only_view(): void
    {
        $vacancy = $this->openVacancy(['hiring_manager_id' => $this->ramon->employee_id]);
        $other = $this->openVacancy(['hiring_manager_id' => $this->outsider->employee_id]);
        $app = $this->evaluated($vacancy);
        $employee = $this->userWithRole('employee');
        $payroll = $this->userWithRole('payroll');

        foreach ([$this->hrStaff, $this->ramon, $this->outsider, $this->maya, $employee, $payroll] as $user) {
            $this->decide($user, $app)->assertForbidden();
        }
        try {
            app(SelectionService::class)->decide($app, 'selected', null, $this->hrStaff);
            $this->fail('HR staff selected');
        } catch (ValidationException) {
        }

        // The hiring manager sees the selection section of their own vacancy only.
        $this->actingAs($this->ramon)->get(route('recruitment.applications.show', $app))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('can.decideSelection', false)->where('selection.summary.submitted_evaluations', 1));
        $this->actingAs($this->outsider)->get(route('recruitment.applications.show', $app))->assertForbidden();
        $this->actingAs($this->dina)->get(route('recruitment.applications.show', $app))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('can.decideSelection', true)->where('selection.evaluationProblem', null));

        $this->assertSame(0, ApplicationSelection::count());
        $this->assertSame(0, $other->fresh()->filled_count);
    }
}
