<?php

namespace Tests\Feature\Recruitment;

use App\Models\ApplicationAssessment;
use App\Models\User;
use App\Services\Recruitment\ApplicationPipelineService;
use App\Services\Recruitment\AssessmentService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;

/**
 * Assessments: creation in the Assessment stage, scored and pass/fail results,
 * the scheduled → in_progress → completed / cancelled rules, and access.
 */
class AssessmentTest extends RecruitmentTestCase
{
    private function create(User $as, $application, array $overrides = [])
    {
        return $this->actingAs($as)->post(route('recruitment.applications.assessments.store', $application), array_merge([
            'assessment_type_id' => $this->assessmentType()->id,
            'scheduled_at' => '2026-10-06 09:00',
            'assessor_employee_id' => $this->maya->employee_id,
            'remarks' => 'Coding test',
        ], $overrides));
    }

    #[Test]
    public function hr_adds_several_assessments_in_the_assessment_stage(): void
    {
        $app = $this->inAssessment($this->openVacancy());

        $this->create($this->hrStaff, $app)->assertSessionHasNoErrors();
        $this->create($this->hrStaff, $app, ['assessment_type_id' => $this->assessmentType('practical_assessment')->id, 'assessor_employee_id' => null, 'scheduled_at' => null])->assertSessionHasNoErrors();

        $assessments = $app->assessments;
        $this->assertSame([['score', 'scheduled', $this->maya->employee_id, $this->hrStaff->id], ['pass_fail', 'scheduled', null, $this->hrStaff->id]],
            $assessments->map(fn ($a) => [$a->result_type, $a->status, $a->assessor_employee_id, $a->created_by])->all());
        $this->assertSame('2026-10-06 09:00', $assessments[0]->scheduled_at->format('Y-m-d H:i'));
    }

    #[Test]
    public function assessments_are_validated_and_need_the_assessment_stage(): void
    {
        $vacancy = $this->openVacancy();
        $interviewing = $this->inInterview($vacancy);
        $this->create($this->hrStaff, $interviewing)->assertSessionHasErrors(['status' => 'Move the application to the Assessment stage before adding assessments.']);

        $app = $this->inAssessment($vacancy);
        $inactive = $this->assessmentType('aptitude_assessment');
        $inactive->update(['is_active' => false]);
        $this->create($this->hrStaff, $app, ['assessment_type_id' => $inactive->id])->assertSessionHasErrors('assessment_type_id');
        $this->create($this->hrStaff, $app, ['assessment_type_id' => null])->assertSessionHasErrors('assessment_type_id');
        $this->create($this->hrStaff, $app, ['assessor_employee_id' => 99999])->assertSessionHasErrors('assessor_employee_id');
        $this->create($this->hrStaff, $app, ['scheduled_at' => 'someday'])->assertSessionHasErrors('scheduled_at');

        app(ApplicationPipelineService::class)->withdraw($app, 'Withdrew', $this->hrStaff);
        $this->create($this->hrStaff, $app)->assertForbidden();

        $this->assertSame(0, ApplicationAssessment::count());
    }

    #[Test]
    public function a_scored_assessment_needs_a_score_within_its_maximum(): void
    {
        $app = $this->inAssessment($this->openVacancy());
        $assessment = app(AssessmentService::class)->create($app, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);
        $complete = fn (array $data) => $this->actingAs($this->hrStaff)->post(route('recruitment.assessments.complete', $assessment), $data);

        $complete([])->assertSessionHasErrors('score');
        $complete(['passed' => true])->assertSessionHasErrors('score');
        $complete(['score' => 80])->assertSessionHasErrors('maximum_score');
        $complete(['score' => 120, 'maximum_score' => 100])->assertSessionHasErrors('score');
        $complete(['score' => -1, 'maximum_score' => 100])->assertSessionHasErrors('score');
        $complete(['score' => 10, 'maximum_score' => 0])->assertSessionHasErrors('maximum_score');
        $this->assertSame('scheduled', $assessment->fresh()->status);

        $complete(['score' => 85.5, 'maximum_score' => 100, 'passed' => true, 'remarks' => 'Solid'])->assertSessionHasNoErrors();
        $assessment->refresh();
        $this->assertSame(['completed', '85.50', '100.00', true, 'Solid', $this->hrStaff->id], [$assessment->status, $assessment->score, $assessment->maximum_score, $assessment->passed, $assessment->remarks, $assessment->completed_by]);
        $this->assertNotNull($assessment->completed_at);
        $this->assertNotNull($assessment->started_at);
    }

    #[Test]
    public function a_pass_fail_assessment_needs_the_outcome_and_the_score_is_optional(): void
    {
        $app = $this->inAssessment($this->openVacancy());
        $assessment = app(AssessmentService::class)->create($app, ['assessment_type_id' => $this->assessmentType('practical_assessment')->id], $this->hrStaff);

        $this->actingAs($this->hrStaff)->post(route('recruitment.assessments.complete', $assessment), [])->assertSessionHasErrors('passed');
        $this->actingAs($this->hrStaff)->post(route('recruitment.assessments.complete', $assessment), ['passed' => 'maybe'])->assertSessionHasErrors('passed');
        $this->actingAs($this->hrStaff)->post(route('recruitment.assessments.complete', $assessment), ['passed' => false])->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertSame(['completed', false, null, null], [$assessment->status, $assessment->passed, $assessment->score, $assessment->maximum_score]);
        // A failed assessment is a recorded result only; it never rejects the application.
        $this->assertSame('shortlisted', $app->fresh()->status);
    }

    #[Test]
    public function changing_the_type_configuration_never_changes_a_recorded_result_type(): void
    {
        $app = $this->inAssessment($this->openVacancy());
        $type = $this->assessmentType();
        $assessment = app(AssessmentService::class)->create($app, ['assessment_type_id' => $type->id], $this->hrStaff);

        $this->actingAs($this->dina)->put(route('recruitment.settings.assessment-types.update', $type), ['name' => $type->name, 'result_type' => 'pass_fail'])->assertSessionHasNoErrors();

        $this->assertSame(['pass_fail', 'score'], [$type->fresh()->result_type, $assessment->fresh()->result_type]);
    }

    #[Test]
    public function status_transitions_are_controlled(): void
    {
        $app = $this->inAssessment($this->openVacancy());
        $service = app(AssessmentService::class);
        $a = $service->create($app, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);
        $b = $service->create($app, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);

        $this->actingAs($this->hrStaff)->post(route('recruitment.assessments.start', $a))->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $a->fresh()->status);
        $this->assertNotNull($a->fresh()->started_at);
        $this->actingAs($this->hrStaff)->post(route('recruitment.assessments.start', $a))->assertSessionHasErrors('status');

        $this->actingAs($this->hrStaff)->put(route('recruitment.assessments.update', $a), ['scheduled_at' => '2026-10-07 13:00', 'assessor_employee_id' => $this->dina->employee_id, 'remarks' => 'Moved'])->assertSessionHasNoErrors();
        $this->assertSame([$this->dina->employee_id, 'Moved', $this->hrStaff->id], [$a->fresh()->assessor_employee_id, $a->fresh()->remarks, $a->fresh()->updated_by]);

        $this->actingAs($this->hrStaff)->post(route('recruitment.assessments.cancel', $b), ['reason' => 'Duplicate'])->assertSessionHasNoErrors();
        $b->refresh();
        $this->assertSame(['cancelled', 'Duplicate', $this->hrStaff->id], [$b->status, $b->cancellation_reason, $b->cancelled_by]);
        $this->assertNotNull($b->cancelled_at);

        $service->complete($a, ['score' => 5, 'maximum_score' => 10], $this->hrStaff);

        // Cancelled → completed, completed → cancelled/started/edited: all refused.
        foreach ([$a, $b] as $final) {
            foreach (['start', 'complete', 'cancel'] as $action) {
                $this->actingAs($this->hrStaff)->post(route("recruitment.assessments.{$action}", $final), ['score' => 1, 'maximum_score' => 2, 'passed' => true])->assertForbidden();
            }
            $this->actingAs($this->hrStaff)->put(route('recruitment.assessments.update', $final), ['remarks' => 'x'])->assertForbidden();
        }
        try {
            $service->complete($b, ['score' => 1, 'maximum_score' => 2], $this->hrStaff);
            $this->fail('cancelled assessment completed');
        } catch (ValidationException) {
        }
        $this->assertSame(['completed', 'cancelled'], [$a->fresh()->status, $b->fresh()->status]);
    }

    #[Test]
    public function a_withdrawn_applications_open_assessment_can_only_be_cancelled(): void
    {
        $app = $this->inAssessment($this->openVacancy());
        $assessment = app(AssessmentService::class)->create($app, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);
        app(ApplicationPipelineService::class)->withdraw($app, 'Moved abroad', $this->hrStaff);

        $this->actingAs($this->hrStaff)->post(route('recruitment.assessments.complete', $assessment), ['score' => 1, 'maximum_score' => 2])->assertSessionHasErrors('status');
        $this->actingAs($this->hrStaff)->post(route('recruitment.assessments.cancel', $assessment))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $assessment->fresh()->status);
    }

    #[Test]
    public function access_hr_manages_hiring_manager_views_others_are_denied(): void
    {
        $vacancy = $this->openVacancy(['hiring_manager_id' => $this->ramon->employee_id]);
        $app = $this->inAssessment($vacancy, [$this->outsider]);
        $assessment = app(AssessmentService::class)->create($app, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);
        $employee = $this->userWithRole('employee');
        $payroll = $this->userWithRole('payroll');

        $this->actingAs($this->ramon)->get(route('recruitment.applications.show', $app))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('assessments', 1)->where('assessments.0.can_update', false)->where('can.createAssessment', false));
        $this->actingAs($this->hrStaff)->get(route('recruitment.applications.show', $app))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('assessments', 1)->where('assessments.0.can_update', true)->where('can.createAssessment', true));

        foreach ([$this->ramon, $this->maya, $this->outsider, $employee, $payroll] as $user) {
            $this->create($user, $app)->assertForbidden();
            $this->actingAs($user)->post(route('recruitment.assessments.start', $assessment))->assertForbidden();
            $this->actingAs($user)->post(route('recruitment.assessments.complete', $assessment), ['score' => 1, 'maximum_score' => 1])->assertForbidden();
            $this->actingAs($user)->post(route('recruitment.assessments.cancel', $assessment))->assertForbidden();
            $this->actingAs($user)->put(route('recruitment.assessments.update', $assessment), [])->assertForbidden();
        }
        // The interview panelist (outsider) still can't open the application page.
        $this->actingAs($this->outsider)->get(route('recruitment.applications.show', $app))->assertForbidden();
        $this->assertSame('scheduled', $assessment->fresh()->status);
    }
}
