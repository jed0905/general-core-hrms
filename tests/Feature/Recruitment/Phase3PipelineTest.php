<?php

namespace Tests\Feature\Recruitment;

use App\Models\ApplicationAssessment;
use App\Models\ApplicationStageHistory;
use App\Models\RecruitmentStage;
use App\Models\Vacancy;
use App\Models\VacancyStage;
use App\Services\Recruitment\ApplicationPipelineService;
use App\Services\Recruitment\AssessmentService;
use App\Services\Recruitment\InterviewService;
use App\Services\Recruitment\VacancyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Shortlisted → Interview → Assessment → Evaluation through ApplicationPipelineService.
 */
class Phase3PipelineTest extends RecruitmentTestCase
{
    private function advanceAs($user, $application, ?int $expected = null)
    {
        return $this->actingAs($user)->post(route('recruitment.applications.advance', $application), [
            'expected_stage_id' => $expected ?? $application->fresh()->current_vacancy_stage_id,
        ]);
    }

    #[Test]
    public function the_template_has_the_six_stages_and_new_vacancies_snapshot_them(): void
    {
        $this->assertSame(
            [['applied', 10], ['screening', 20], ['shortlisted', 30], ['interview', 40], ['assessment', 50], ['evaluation', 60]],
            RecruitmentStage::orderBy('sort_order')->get()->map(fn ($s) => [$s->stage_type, $s->sort_order])->all()
        );
        $this->assertSame(['applied', 'screening', 'shortlisted', 'interview', 'assessment', 'evaluation'], $this->openVacancy()->stages->pluck('stage_type')->all());
    }

    #[Test]
    public function a_vacancy_opened_before_phase_3_keeps_its_three_stage_snapshot(): void
    {
        // Simulate a vacancy that opened with the phase 2 template.
        RecruitmentStage::whereIn('stage_type', RecruitmentStage::POST_SHORTLIST_TYPES)->update(['is_active' => false]);
        $old = $this->openVacancy();
        RecruitmentStage::whereIn('stage_type', RecruitmentStage::POST_SHORTLIST_TYPES)->update(['is_active' => true]);

        // Re-opening never re-snapshots.
        app(VacancyService::class)->transition($old, Vacancy::STATUS_ON_HOLD, $this->hrStaff, 'pause');
        app(VacancyService::class)->transition($old, Vacancy::STATUS_OPEN, $this->hrStaff);
        $this->assertSame(['applied', 'screening', 'shortlisted'], $old->fresh()->stages->pluck('stage_type')->all());

        $app = $this->shortlisted($old);
        $this->advanceAs($this->hrStaff, $app)->assertSessionHasErrors(['stage' => '"Shortlisted" is the last stage of this vacancy\'s pipeline.']);

        $this->assertSame(6, $this->openVacancy()->stages()->count(), 'new vacancies get the new stages');
    }

    #[Test]
    public function the_full_phase_3_path_with_prerequisites_and_history(): void
    {
        $vacancy = $this->openVacancy();
        $app = $this->shortlisted($vacancy);

        // Shortlisted → Interview: explicit action; status stays "shortlisted".
        $this->advanceAs($this->hrStaff, $app)->assertSessionHasNoErrors();
        $app->refresh();
        $this->assertSame([$this->stage($vacancy, 'interview')->id, 'shortlisted'], [$app->current_vacancy_stage_id, $app->status]);

        // Interview → Assessment needs a completed interview: none, scheduled and cancelled don't count.
        $this->advanceAs($this->hrStaff, $app)->assertSessionHasErrors(['stage' => 'At least one interview must be completed before moving to "Assessment".']);
        $cancelled = $this->scheduleInterview($app, [$this->maya]);
        app(InterviewService::class)->cancel($cancelled, 'Clash', $this->hrStaff);
        $this->scheduleInterview($app, [$this->maya], ['start_time' => '14:00']);
        $this->advanceAs($this->hrStaff, $app)->assertSessionHasErrors('stage');
        $this->assertSame($this->stage($vacancy, 'interview')->id, $app->fresh()->current_vacancy_stage_id);

        $this->completedInterview($app, [$this->outsider], ['scheduled_date' => '2026-10-06']);
        $this->advanceAs($this->hrStaff, $app)->assertSessionHasNoErrors();
        $this->assertSame($this->stage($vacancy, 'assessment')->id, $app->fresh()->current_vacancy_stage_id);

        // Assessment → Evaluation needs a completed assessment and none still open.
        $this->advanceAs($this->hrStaff, $app)->assertSessionHasErrors(['stage' => 'At least one assessment must be completed before moving to "Evaluation".']);
        $service = app(AssessmentService::class);
        $first = $service->create($app, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);
        $this->advanceAs($this->hrStaff, $app)->assertSessionHasErrors(['stage' => 'Complete or cancel the open assessments before moving to "Evaluation".']);
        $service->complete($first, ['score' => 70, 'maximum_score' => 100], $this->hrStaff);
        $second = $service->create($app, ['assessment_type_id' => $this->assessmentType('practical_assessment')->id], $this->hrStaff);
        $service->start($second, $this->hrStaff);
        $this->advanceAs($this->hrStaff, $app)->assertSessionHasErrors('stage');
        $service->cancel($second, 'Not needed', $this->hrStaff);
        $this->advanceAs($this->hrStaff, $app)->assertSessionHasNoErrors();

        $app->refresh();
        $this->assertSame([$this->stage($vacancy, 'evaluation')->id, 'shortlisted'], [$app->current_vacancy_stage_id, $app->status]);

        // Evaluation is the end of the pipeline in this phase.
        $this->advanceAs($this->hrStaff, $app)->assertSessionHasErrors(['stage' => '"Evaluation" is the last stage of this vacancy\'s pipeline.']);

        $this->assertSame(
            [['moved', 'Shortlisted', 'Interview', 'shortlisted', 'shortlisted'], ['moved', 'Interview', 'Assessment', 'shortlisted', 'shortlisted'], ['moved', 'Assessment', 'Evaluation', 'shortlisted', 'shortlisted']],
            $app->history->slice(3)->map(fn ($h) => [$h->action, $h->from_stage_name, $h->to_stage_name, $h->from_status, $h->to_status])->values()->all()
        );
        $this->assertSame(6, $app->history()->count());
    }

    #[Test]
    public function creating_interviews_or_assessments_never_moves_the_application(): void
    {
        $vacancy = $this->openVacancy();
        $app = $this->inInterview($vacancy);
        $this->completedInterview($app, [$this->maya]);
        $this->assertSame($this->stage($vacancy, 'interview')->id, $app->fresh()->current_vacancy_stage_id);

        $app = $this->moveOn($app);
        $assessment = app(AssessmentService::class)->create($app, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);
        app(AssessmentService::class)->complete($assessment, ['score' => 9, 'maximum_score' => 10], $this->hrStaff);
        $this->assertSame($this->stage($vacancy, 'assessment')->id, $app->fresh()->current_vacancy_stage_id);
    }

    #[Test]
    public function stale_moves_are_refused_without_extra_history(): void
    {
        $vacancy = $this->openVacancy();
        $app = $this->shortlisted($vacancy);
        $shortlistedStage = $app->current_vacancy_stage_id;

        $this->advanceAs($this->hrStaff, $app, $shortlistedStage)->assertSessionHasNoErrors();
        $this->advanceAs($this->dina, $app, $shortlistedStage)->assertSessionHasErrors(['stage' => 'This application has already moved to another stage. Refresh to see its current stage.']);

        $this->assertSame(1, $app->history()->where('to_stage_name', 'Interview')->count());
    }

    #[Test]
    public function rejected_and_withdrawn_applications_cannot_move_into_phase_3_stages(): void
    {
        $vacancy = $this->openVacancy();

        $rejected = $this->shortlisted($vacancy);
        app(ApplicationPipelineService::class)->reject($rejected, $this->reason()->id, $this->hrStaff);
        $this->advanceAs($this->hrStaff, $rejected)->assertForbidden();

        $withdrawn = $this->inInterview($vacancy);
        app(ApplicationPipelineService::class)->withdraw($withdrawn, 'Took another offer', $this->hrStaff);
        $this->advanceAs($this->hrStaff, $withdrawn)->assertForbidden();

        $this->expectException(ValidationException::class);
        app(ApplicationPipelineService::class)->advance($withdrawn, $withdrawn->current_vacancy_stage_id, $this->hrStaff);
    }

    #[Test]
    public function cancelled_and_filled_vacancies_freeze_forward_moves_but_allow_reject_and_withdraw(): void
    {
        foreach (['cancel' => Vacancy::STATUS_CANCELLED, 'fill' => Vacancy::STATUS_FILLED] as $status) {
            $vacancy = $this->openVacancy();
            $app = $this->shortlisted($vacancy);
            $interviewing = $this->inInterview($vacancy);
            $this->completedInterview($interviewing, [$this->maya]);
            Vacancy::whereKey($vacancy->id)->update(['status' => $status]);

            $this->advanceAs($this->hrStaff, $app)->assertSessionHasErrors('stage');
            $this->advanceAs($this->hrStaff, $interviewing)->assertSessionHasErrors('stage');
            $this->assertSame($this->stage($vacancy, 'shortlisted')->id, $app->fresh()->current_vacancy_stage_id);

            $this->actingAs($this->hrStaff)->post(route('recruitment.applications.interviews.store', $interviewing), $this->interviewPayload([$this->maya], ['scheduled_date' => '2026-12-01']))
                ->assertSessionHasErrors('stage');

            $this->actingAs($this->hrStaff)->post(route('recruitment.applications.reject', $app), ['rejection_reason_id' => $this->reason('position_filled')->id])->assertSessionHasNoErrors();
            $this->actingAs($this->hrStaff)->post(route('recruitment.applications.withdraw', $interviewing), ['reason' => 'Vacancy closed'])->assertSessionHasNoErrors();
            Carbon::setTestNow('2026-10-04 09:00:00');
        }
    }

    #[Test]
    public function only_users_with_move_permission_can_advance(): void
    {
        $vacancy = $this->openVacancy(['hiring_manager_id' => $this->ramon->employee_id]);
        $app = $this->shortlisted($vacancy);

        $this->advanceAs($this->ramon, $app)->assertForbidden(); // hiring manager: view only
        $this->advanceAs($this->maya, $app)->assertForbidden();
        $this->assertSame($this->stage($vacancy, 'shortlisted')->id, $app->fresh()->current_vacancy_stage_id);

        $this->expectException(ValidationException::class);
        app(ApplicationPipelineService::class)->advance($app, $app->current_vacancy_stage_id, $this->maya);
    }

    #[Test]
    public function the_application_page_explains_a_blocked_move(): void
    {
        $vacancy = $this->openVacancy();
        $app = $this->inInterview($vacancy);

        $this->actingAs($this->hrStaff)->get(route('recruitment.applications.show', $app))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('app/Recruitment/Applications/Show', false)
                ->where('can.advance', true)
                ->where('can.scheduleInterview', true)
                ->where('can.createAssessment', false)
                ->where('nextStage.stage_type', 'assessment')
                ->where('nextStageBlocker', 'At least one interview must be completed before moving to "Assessment".'));
    }

    #[Test]
    public function stage_history_stays_immutable(): void
    {
        $app = $this->inInterview($this->openVacancy());
        $history = ApplicationStageHistory::where('application_id', $app->id)->latest('id')->first();

        try {
            $history->update(['remarks' => 'edited']);
            $this->fail('history was updated');
        } catch (LogicException) {
        }
        $this->expectException(LogicException::class);
        $history->delete();
    }

    #[Test]
    public function a_vacancy_without_an_assessment_stage_moves_from_interview_straight_to_evaluation(): void
    {
        RecruitmentStage::where('stage_type', RecruitmentStage::TYPE_ASSESSMENT)->update(['is_active' => false]);
        $vacancy = $this->openVacancy();
        $this->assertSame(['applied', 'screening', 'shortlisted', 'interview', 'evaluation'], $vacancy->stages->pluck('stage_type')->all());

        $app = $this->inInterview($vacancy);
        $this->advanceAs($this->hrStaff, $app)->assertSessionHasErrors('stage');
        $this->completedInterview($app, [$this->maya]);
        $this->advanceAs($this->hrStaff, $app)->assertSessionHasNoErrors();
        $this->assertSame('evaluation', VacancyStage::find($app->fresh()->current_vacancy_stage_id)->stage_type);
        $this->assertSame(0, ApplicationAssessment::count());
        $this->assertSame(0, DB::table('application_assessments')->count());
    }
}
