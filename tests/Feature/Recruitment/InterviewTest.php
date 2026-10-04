<?php

namespace Tests\Feature\Recruitment;

use App\Models\ApplicationInterview;
use App\Models\Employee;
use App\Models\InterviewPanelist;
use App\Models\User;
use App\Services\Recruitment\ApplicationPipelineService;
use App\Services\Recruitment\InterviewService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Interview scheduling, panels, rescheduling, cancellation, completion,
 * double booking and access (HR, hiring manager, panelist, outsider).
 */
class InterviewTest extends RecruitmentTestCase
{
    private User $eve; // a plain employee asked to sit on a panel

    protected function setUp(): void
    {
        parent::setUp();
        $this->eve = $this->userWithRole('employee', ['emp_first_name' => 'Eve', 'emp_last_name' => 'Engineer']);
    }

    private function schedule(User $as, $application, array $panelists, array $overrides = [])
    {
        return $this->actingAs($as)->post(route('recruitment.applications.interviews.store', $application), $this->interviewPayload($panelists, $overrides));
    }

    #[Test]
    public function hr_schedules_several_interviews_with_multiple_panelists(): void
    {
        $app = $this->inInterview($this->openVacancy());

        $this->schedule($this->hrStaff, $app, [$this->maya, $this->eve], ['primary_panelist_id' => $this->eve->employee_id, 'instructions' => 'Bring portfolio'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->schedule($this->hrStaff, $app, [$this->outsider], ['interview_type_id' => $this->interviewType('technical_interview')->id, 'mode' => 'in_person', 'meeting_url' => null, 'location' => 'Room 4', 'start_time' => '13:00'])
            ->assertSessionHasNoErrors();
        $this->schedule($this->hrStaff, $app, [$this->dina], ['interview_type_id' => $this->interviewType('final_interview')->id, 'mode' => 'phone', 'meeting_url' => null, 'scheduled_date' => '2026-10-07'])
            ->assertSessionHasNoErrors();

        $interviews = $app->interviews()->with('panelists')->get();
        $this->assertSame([1, 2, 3], $interviews->pluck('round')->all());
        $this->assertSame(['video', 'in_person', 'phone'], $interviews->pluck('mode')->all());

        $first = $interviews[0];
        $this->assertSame(['scheduled', '2026-10-05 10:00:00', '2026-10-05 11:00:00', 60, 'https://meet.example.test/abc', null, $this->hrStaff->id],
            [$first->status, $first->starts_at->format('Y-m-d H:i:s'), $first->ends_at->format('Y-m-d H:i:s'), $first->duration_minutes, $first->meeting_url, $first->location, $first->created_by]);
        $this->assertEqualsCanonicalizing([$this->maya->employee_id, $this->eve->employee_id], $first->panelists->pluck('employee_id')->all());
        $this->assertSame($this->eve->employee_id, $first->panelists->firstWhere('is_primary', true)->employee_id);
        $this->assertSame(['Room 4', null], [$interviews[1]->location, $interviews[1]->meeting_url]);
        $this->assertSame([null, null], [$interviews[2]->location, $interviews[2]->meeting_url]);
    }

    #[Test]
    public function scheduling_is_validated(): void
    {
        $app = $this->inInterview($this->openVacancy());
        $inactive = $this->interviewType('panel_interview');
        $inactive->update(['is_active' => false]);
        $terminated = Employee::factory()->create(['status' => 'terminated']);

        $this->schedule($this->hrStaff, $app, [$this->maya], ['meeting_url' => null])->assertSessionHasErrors('meeting_url');
        $this->schedule($this->hrStaff, $app, [$this->maya], ['meeting_url' => 'not a url'])->assertSessionHasErrors('meeting_url');
        $this->schedule($this->hrStaff, $app, [$this->maya], ['mode' => 'in_person', 'location' => ''])->assertSessionHasErrors('location');
        $this->schedule($this->hrStaff, $app, [$this->maya], ['mode' => 'carrier_pigeon'])->assertSessionHasErrors('mode');
        $this->schedule($this->hrStaff, $app, [$this->maya], ['interview_type_id' => $inactive->id])->assertSessionHasErrors('interview_type_id');
        $this->schedule($this->hrStaff, $app, [$this->maya], ['interview_type_id' => 99999])->assertSessionHasErrors('interview_type_id');
        $this->schedule($this->hrStaff, $app, [], [])->assertSessionHasErrors('panelist_ids');
        $this->schedule($this->hrStaff, $app, [$terminated->id])->assertSessionHasErrors('panelist_ids.0');
        $this->schedule($this->hrStaff, $app, [99999])->assertSessionHasErrors('panelist_ids.0');
        $this->schedule($this->hrStaff, $app, [$this->maya, $this->maya])->assertSessionHasErrors('panelist_ids.0');
        $this->schedule($this->hrStaff, $app, [$this->maya], ['primary_panelist_id' => $this->eve->employee_id])->assertSessionHasErrors('primary_panelist_id');
        $this->schedule($this->hrStaff, $app, [$this->maya], ['scheduled_date' => '2026-10-03'])->assertSessionHasErrors('scheduled_date');
        $this->schedule($this->hrStaff, $app, [$this->maya], ['scheduled_date' => '2026-02-30'])->assertSessionHasErrors('scheduled_date');
        $this->schedule($this->hrStaff, $app, [$this->maya], ['start_time' => '25:00'])->assertSessionHasErrors('start_time');
        $this->schedule($this->hrStaff, $app, [$this->maya], ['duration_minutes' => 5])->assertSessionHasErrors('duration_minutes');
        $this->schedule($this->hrStaff, $app, [$this->maya], ['duration_minutes' => 600])->assertSessionHasErrors('duration_minutes');

        $this->assertSame(0, ApplicationInterview::count());
    }

    #[Test]
    public function interviews_need_the_interview_stage_and_a_live_application(): void
    {
        $vacancy = $this->openVacancy();
        $shortlisted = $this->shortlisted($vacancy);
        $this->schedule($this->hrStaff, $shortlisted, [$this->maya])->assertSessionHasErrors(['status' => 'Move the application to the Interview stage before scheduling interviews.']);

        $rejected = $this->inInterview($vacancy);
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.reject', $rejected), ['rejection_reason_id' => $this->reason()->id]);
        $this->schedule($this->hrStaff, $rejected, [$this->maya])->assertForbidden();

        $this->assertSame(0, ApplicationInterview::count());
    }

    #[Test]
    public function a_panelist_cannot_be_double_booked_but_cancelled_and_completed_interviews_free_the_slot(): void
    {
        $vacancy = $this->openVacancy();
        $a = $this->inInterview($vacancy);
        $b = $this->inInterview($vacancy);

        $first = $this->scheduleInterview($a, [$this->maya]); // 10:00–11:00

        $this->schedule($this->hrStaff, $b, [$this->eve, $this->maya], ['start_time' => '10:30'])
            ->assertSessionHasErrors(['panelist_ids' => 'Already booked for an overlapping interview: Maya Manager.']);
        $this->schedule($this->hrStaff, $b, [$this->maya], ['start_time' => '09:30', 'duration_minutes' => 120])->assertSessionHasErrors('panelist_ids');
        // Back-to-back is fine; another panelist at the same time is fine.
        $this->schedule($this->hrStaff, $b, [$this->maya], ['start_time' => '11:00'])->assertSessionHasNoErrors();
        $this->schedule($this->hrStaff, $b, [$this->eve], ['start_time' => '10:00', 'scheduled_date' => '2026-10-06'])->assertSessionHasNoErrors();
        // The same applicant can't be in two interviews at once.
        $this->schedule($this->hrStaff, $a, [$this->outsider], ['start_time' => '10:15'])->assertSessionHasErrors('start_time');

        app(InterviewService::class)->cancel($first, 'Moved', $this->hrStaff);
        $this->schedule($this->hrStaff, $this->inInterview($vacancy), [$this->maya], ['start_time' => '10:00'])->assertSessionHasNoErrors();

        // A completed interview never blocks.
        $done = $this->completedInterview($this->inInterview($vacancy), [$this->dina], ['scheduled_date' => '2026-10-08']);
        Carbon::setTestNow('2026-10-08 08:00:00');
        $this->schedule($this->hrStaff, $this->inInterview($vacancy), [$this->dina], ['scheduled_date' => '2026-10-08'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $done->status);
    }

    #[Test]
    public function rescheduling_keeps_the_original_time_and_checks_double_booking(): void
    {
        $vacancy = $this->openVacancy();
        $interview = $this->scheduleInterview($this->inInterview($vacancy), [$this->maya]);
        $this->scheduleInterview($this->inInterview($vacancy), [$this->maya], ['start_time' => '15:00']);
        $createdAt = $interview->created_at;

        $this->actingAs($this->hrStaff)->post(route('recruitment.interviews.reschedule', $interview), ['scheduled_date' => '2026-10-05', 'start_time' => '15:30', 'duration_minutes' => 30])
            ->assertSessionHasErrors('panelist_ids');
        $this->actingAs($this->hrStaff)->post(route('recruitment.interviews.reschedule', $interview), ['scheduled_date' => '2026-10-05', 'start_time' => '10:00', 'duration_minutes' => 60])
            ->assertSessionHasErrors('scheduled_date');

        Carbon::setTestNow('2026-10-04 11:00:00');
        $this->actingAs($this->dina)->post(route('recruitment.interviews.reschedule', $interview), ['scheduled_date' => '2026-10-06', 'start_time' => '09:00', 'duration_minutes' => 45, 'reason' => 'Panelist travelling'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->hrStaff)->post(route('recruitment.interviews.reschedule', $interview), ['scheduled_date' => '2026-10-07', 'start_time' => '09:00', 'duration_minutes' => 45])
            ->assertSessionHasNoErrors();

        $interview->refresh();
        $this->assertSame(['2026-10-07 09:00', '2026-10-07 09:45', 45, 'scheduled'], [$interview->starts_at->format('Y-m-d H:i'), $interview->ends_at->format('Y-m-d H:i'), $interview->duration_minutes, $interview->status]);
        $this->assertTrue($createdAt->equalTo($interview->created_at));

        $log = $interview->reschedules;
        $this->assertSame([
            ['2026-10-05 10:00', '2026-10-06 09:00', 'Panelist travelling', $this->dina->id],
            ['2026-10-06 09:00', '2026-10-07 09:00', null, $this->hrStaff->id],
        ], $log->map(fn ($r) => [$r->previous_starts_at->format('Y-m-d H:i'), $r->new_starts_at->format('Y-m-d H:i'), $r->reason, $r->rescheduled_by])->all());
        $this->assertSame('2026-10-04 11:00:00', $log[0]->rescheduled_at->format('Y-m-d H:i:s'));

        $this->expectException(LogicException::class);
        $log[0]->update(['reason' => 'rewritten']);
    }

    #[Test]
    public function cancellation_and_completion_follow_the_state_rules(): void
    {
        $app = $this->inInterview($this->openVacancy());
        $cancelled = $this->scheduleInterview($app, [$this->maya]);
        $completed = $this->scheduleInterview($app, [$this->eve], ['start_time' => '14:00']);

        // Completing before the interview starts is refused.
        $this->actingAs($this->hrStaff)->post(route('recruitment.interviews.complete', $completed))->assertSessionHasErrors('status');

        $this->actingAs($this->hrStaff)->post(route('recruitment.interviews.cancel', $cancelled), ['reason' => 'Applicant asked'])->assertSessionHasNoErrors();
        $cancelled->refresh();
        $this->assertSame(['cancelled', $this->hrStaff->id, 'Applicant asked'], [$cancelled->status, $cancelled->cancelled_by, $cancelled->cancellation_reason]);
        $this->assertNotNull($cancelled->cancelled_at);

        Carbon::setTestNow('2026-10-05 15:30:00');
        $this->actingAs($this->hrStaff)->post(route('recruitment.interviews.complete', $completed), ['remarks' => 'Strong'])->assertSessionHasNoErrors();
        $completed->refresh();
        $this->assertSame(['completed', $this->hrStaff->id, 'Strong', '2026-10-05 15:30:00'], [$completed->status, $completed->completed_by, $completed->completion_remarks, $completed->completed_at->format('Y-m-d H:i:s')]);

        // Final states: no route accepts them (policy) and the service refuses too.
        foreach ([$cancelled, $completed] as $interview) {
            $this->actingAs($this->hrStaff)->post(route('recruitment.interviews.complete', $interview))->assertForbidden();
            $this->actingAs($this->hrStaff)->post(route('recruitment.interviews.cancel', $interview))->assertForbidden();
            $this->actingAs($this->hrStaff)->post(route('recruitment.interviews.reschedule', $interview), ['scheduled_date' => '2026-10-09', 'start_time' => '09:00', 'duration_minutes' => 30])->assertForbidden();
            $this->actingAs($this->hrStaff)->put(route('recruitment.interviews.update', $interview), $this->interviewPayload([$this->maya]))->assertForbidden();
        }

        foreach (['complete' => fn ($i) => app(InterviewService::class)->complete($i, null, $this->hrStaff), 'cancel' => fn ($i) => app(InterviewService::class)->cancel($i, null, $this->hrStaff)] as $action) {
            foreach ([$cancelled, $completed] as $interview) {
                try {
                    $action($interview);
                    $this->fail('a final interview changed state');
                } catch (ValidationException) {
                }
            }
        }
        $this->assertSame(['cancelled', 'completed'], [$cancelled->fresh()->status, $completed->fresh()->status]);
        $this->assertSame(2, ApplicationInterview::count(), 'nothing deleted');
    }

    #[Test]
    public function a_rejected_application_can_still_have_its_interview_cancelled_but_not_completed(): void
    {
        $app = $this->inInterview($this->openVacancy());
        $one = $this->scheduleInterview($app, [$this->maya]);
        $two = $this->scheduleInterview($app, [$this->eve], ['start_time' => '14:00']);
        app(ApplicationPipelineService::class)->reject($app, $this->reason()->id, $this->hrStaff);

        Carbon::setTestNow('2026-10-05 16:00:00');
        $this->actingAs($this->hrStaff)->post(route('recruitment.interviews.complete', $one))->assertSessionHasErrors('status');
        $this->actingAs($this->hrStaff)->post(route('recruitment.interviews.cancel', $two))->assertSessionHasNoErrors();
        $this->assertSame(['scheduled', 'cancelled'], [$one->fresh()->status, $two->fresh()->status]);
    }

    #[Test]
    public function editing_details_and_panel_of_a_scheduled_interview(): void
    {
        $vacancy = $this->openVacancy();
        $interview = $this->scheduleInterview($this->inInterview($vacancy), [$this->maya, $this->eve]);
        $this->scheduleInterview($this->inInterview($vacancy), [$this->outsider]);

        // Adding a panelist who is busy at that time is refused.
        $this->actingAs($this->hrStaff)->put(route('recruitment.interviews.update', $interview), $this->interviewPayload([$this->maya, $this->outsider]))
            ->assertSessionHasErrors('panelist_ids');

        $this->actingAs($this->hrStaff)->put(route('recruitment.interviews.update', $interview), $this->interviewPayload([$this->eve, $this->dina], [
            'mode' => 'in_person', 'location' => 'HQ 3F', 'meeting_url' => null, 'primary_panelist_id' => $this->dina->employee_id,
            'scheduled_date' => '2027-01-01', // ignored: times only change through reschedule
        ]))->assertSessionHasNoErrors();

        $interview->refresh();
        $this->assertSame(['in_person', 'HQ 3F', null, '2026-10-05 10:00', $this->hrStaff->id], [$interview->mode, $interview->location, $interview->meeting_url, $interview->starts_at->format('Y-m-d H:i'), $interview->updated_by]);
        $this->assertEqualsCanonicalizing([$this->eve->employee_id, $this->dina->employee_id], $interview->panelists()->pluck('employee_id')->all());
        $this->assertSame($this->dina->employee_id, InterviewPanelist::where('application_interview_id', $interview->id)->where('is_primary', true)->value('employee_id'));
    }

    #[Test]
    public function access_hr_hiring_manager_panelist_and_outsiders(): void
    {
        $vacancy = $this->openVacancy(['hiring_manager_id' => $this->ramon->employee_id]);
        $app = $this->inInterview($vacancy);
        $interview = $this->scheduleInterview($app, [$this->eve]);
        $other = $this->scheduleInterview($this->inInterview($this->openVacancy()), [$this->maya]);

        // HR (permission) sees and manages everything.
        $this->actingAs($this->hrStaff)->get(route('recruitment.interviews.show', $interview))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('app/Recruitment/Interviews/Show', false)->where('can.update', true)->where('can.viewApplication', true));

        // Hiring manager: views this vacancy's interviews, can't manage them, can't see another vacancy's.
        $this->actingAs($this->ramon)->get(route('recruitment.interviews.show', $interview))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('can.update', false)->where('can.viewEvaluations', true));
        $this->actingAs($this->ramon)->get(route('recruitment.interviews.show', $other))->assertForbidden();
        $this->actingAs($this->ramon)->post(route('recruitment.interviews.cancel', $interview))->assertForbidden();
        $this->schedule($this->ramon, $app, [$this->maya], ['start_time' => '15:00'])->assertForbidden();

        // Panelist: only the interview they sit on; nothing of the application itself.
        $this->actingAs($this->eve)->get(route('recruitment.interviews.show', $interview))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('can.update', false)->where('can.viewApplication', false)->where('can.viewEvaluations', false)
                ->where('interview.application.applicant.first_name', 'Juan')->missing('interview.application.applicant.email'));
        $this->actingAs($this->eve)->get(route('recruitment.interviews.show', $other))->assertForbidden();
        $this->actingAs($this->eve)->get(route('recruitment.applications.show', $app))->assertForbidden();
        $this->actingAs($this->eve)->get(route('recruitment.applicants.show', $app->applicant_id))->assertForbidden();
        $this->actingAs($this->eve)->get(route('recruitment.applications.index'))->assertForbidden();
        $this->actingAs($this->eve)->get(route('recruitment.vacancies.show', $vacancy))->assertForbidden();
        $this->actingAs($this->eve)->get(route('recruitment.settings.index'))->assertForbidden();
        $this->actingAs($this->eve)->post(route('recruitment.interviews.complete', $interview))->assertForbidden();

        // Unrelated supervisor sees nothing.
        $this->actingAs($this->outsider)->get(route('recruitment.interviews.show', $interview))->assertForbidden();
    }

    #[Test]
    public function my_interviews_lists_only_the_users_own_panels(): void
    {
        $vacancy = $this->openVacancy();
        $upcoming = $this->scheduleInterview($this->inInterview($vacancy), [$this->eve, $this->maya]);
        $cancelled = $this->scheduleInterview($this->inInterview($vacancy), [$this->eve], ['scheduled_date' => '2026-10-06']);
        app(InterviewService::class)->cancel($cancelled, null, $this->hrStaff);
        $this->scheduleInterview($this->inInterview($vacancy), [$this->outsider], ['scheduled_date' => '2026-10-07']);

        $this->actingAs($this->eve)->get(route('recruitment.interviews.mine'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('app/Recruitment/Interviews/Mine', false)
                ->has('interviews.data', 1)->where('interviews.data.0.id', $upcoming->id));
        $this->actingAs($this->eve)->get(route('recruitment.interviews.mine', ['scope' => 'cancelled']))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('interviews.data', 1)->where('interviews.data.0.id', $cancelled->id));
        $this->actingAs($this->eve)->get(route('recruitment.interviews.mine', ['scope' => 'all', 'from' => '2026-10-06', 'to' => '2026-10-31']))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('interviews.data', 1)->where('interviews.data.0.id', $cancelled->id));
        $this->actingAs($this->hrStaff)->get(route('recruitment.interviews.mine'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('interviews.data', 0));

        // The navigation shows My Interviews to panelists only, without opening the dashboard.
        $this->actingAs($this->eve)->get(route('recruitment.interviews.mine'))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('recruitmentAccess.myInterviews', true)->where('recruitmentAccess.dashboard', false));
        $this->actingAs($this->eve)->get(route('recruitment.dashboard'))->assertForbidden();
    }
}
