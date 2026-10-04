<?php

namespace Tests\Feature\Onboarding;

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\Onboarding;
use App\Models\OnboardingEvent;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplateTask;
use App\Services\Onboarding\OnboardingService;
use App\Services\Onboarding\OnboardingTaskService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Starting onboarding from a template, completion rules, cancellation, audit.
 */
class OnboardingCaseTest extends OnboardingTestCase
{
    private function start($as, array $data)
    {
        return $this->actingAs($as)->post(route('people.onboarding.store'), $data);
    }

    /** Complete every required task (and verify), as the right people. */
    private function finishRequired(Onboarding $onboarding): void
    {
        Storage::fake('local');
        $tasks = app(OnboardingTaskService::class);
        $tasks->complete($this->task($onboarding, 'Submit government ID'), ['file' => UploadedFile::fake()->create('id.pdf', 20, 'application/pdf')], $this->nina);
        $tasks->verify($this->task($onboarding, 'Submit government ID'), $this->hr);
        $tasks->complete($this->task($onboarding, 'Prepare workstation'), [], $this->hr);
        $tasks->complete($this->task($onboarding, 'Meet supervisor'), [], $this->sam);
        $tasks->complete($this->task($onboarding, 'Set up IT accounts'), [], $this->ivan);
    }

    #[Test]
    public function hr_starts_onboarding_and_the_template_is_snapshotted(): void
    {
        $template = $this->template();
        $this->start($this->hr, ['employee_id' => $this->nina->employee_id, 'onboarding_template_id' => $template->id, 'start_date' => '2026-10-15', 'target_completion_date' => '2026-11-15', 'notes' => 'Remote first week'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $onboarding = Onboarding::sole();
        $this->assertSame([$this->nina->employee_id, 'pending', 'Standard New Employee Onboarding', '2026-10-15', $this->hr->id],
            [$onboarding->employee_id, $onboarding->status, $onboarding->template_name, $onboarding->start_date->toDateString(), $onboarding->created_by]);

        // Inactive template tasks are not copied; assignees and due dates are resolved and stored.
        $this->assertSame([
            ['Submit government ID', 'employee', $this->nina->employee_id, '2026-10-12', true, true],
            ['Prepare workstation', 'hr', null, '2026-10-15', true, false],
            ['Meet supervisor', 'supervisor', $this->sam->employee_id, '2026-10-15', true, true],
            ['Set up IT accounts', 'specific_employee', $this->ivan->employee_id, '2026-10-16', true, true],
            ['Review company policies', 'employee', $this->nina->employee_id, '2026-10-22', false, true],
        ], $onboarding->tasks->map(fn ($t) => [$t->title, $t->assignee_type, $t->assignee_employee_id, $t->due_date->toDateString(), $t->is_required, $t->employee_visible])->all());

        // Later template edits and supervisor changes never rewrite the case.
        OnboardingTemplateTask::where('title', 'Submit government ID')->update(['title' => 'Submit government ID and tax ID', 'due_offset_days' => -10]);
        Employee::whereKey($this->nina->employee_id)->update(['supervisor_id' => $this->ollie->employee_id]);
        $task = $this->task($onboarding, 'Submit government ID');
        $this->assertSame('2026-10-12', $task->due_date->toDateString());
        $this->assertSame($this->sam->employee_id, $this->task($onboarding, 'Meet supervisor')->assignee_employee_id);

        $this->assertSame(['created', 'task_created', 'task_assigned'], $onboarding->events->pluck('event')->unique()->values()->all());
        $this->assertSame(11, $onboarding->events()->count(), 'created + 2 per task');
    }

    #[Test]
    public function starting_is_validated_and_only_for_current_employees(): void
    {
        $template = $this->template();
        $inactive = $this->template(['name' => 'Old', 'is_active' => false]);
        $terminated = Employee::factory()->create(['status' => 'terminated']);

        $this->start($this->hr, ['employee_id' => $terminated->id, 'onboarding_template_id' => $template->id, 'start_date' => '2026-10-15'])->assertSessionHasErrors('employee_id');
        $this->start($this->hr, ['employee_id' => $this->nina->employee_id, 'onboarding_template_id' => $inactive->id, 'start_date' => '2026-10-15'])->assertSessionHasErrors('onboarding_template_id');
        $this->start($this->hr, ['employee_id' => 99999, 'onboarding_template_id' => $template->id, 'start_date' => '2026-10-15'])->assertSessionHasErrors('employee_id');
        $this->start($this->hr, ['employee_id' => $this->nina->employee_id, 'onboarding_template_id' => $template->id, 'start_date' => '2026-10-15', 'target_completion_date' => '2026-10-01'])->assertSessionHasErrors('target_completion_date');

        // Applicants are not employees: onboarding takes an employee id only.
        $applicant = Applicant::create(['applicant_number' => 'APP-X', 'first_name' => 'Apple', 'last_name' => 'Cant', 'status' => 'active', 'privacy_consent' => true]);
        $this->start($this->hr, ['employee_id' => $applicant->id + 1000, 'onboarding_template_id' => $template->id, 'start_date' => '2026-10-15'])->assertSessionHasErrors('employee_id');

        $this->assertSame(0, Onboarding::count());
    }

    #[Test]
    public function an_employee_has_at_most_one_active_onboarding_and_reonboarding_is_explicit(): void
    {
        $template = $this->template();
        $first = $this->startFor($this->nina, $template);

        $this->start($this->hr, ['employee_id' => $this->nina->employee_id, 'onboarding_template_id' => $template->id, 'start_date' => '2026-10-15'])
            ->assertSessionHasErrors(['employee_id' => 'This employee already has an active onboarding.']);

        // The database refuses a second active case too.
        try {
            Onboarding::create(['employee_id' => $this->nina->employee_id, 'template_name' => 'x', 'status' => 'in_progress', 'start_date' => '2026-10-15']);
            $this->fail('two active onboardings');
        } catch (UniqueConstraintViolationException) {
        }

        $this->finishRequired($first);
        app(OnboardingService::class)->complete($first, $this->hr);

        $this->start($this->hr, ['employee_id' => $this->nina->employee_id, 'onboarding_template_id' => $template->id, 'start_date' => '2027-01-04'])
            ->assertSessionHasErrors(['employee_id' => 'This employee already completed onboarding. Confirm re-onboarding to start another.']);
        $this->start($this->hr, ['employee_id' => $this->nina->employee_id, 'onboarding_template_id' => $template->id, 'start_date' => '2027-01-04', 'confirm_reonboarding' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame(['completed', 'pending'], Onboarding::where('employee_id', $this->nina->employee_id)->orderBy('id')->pluck('status')->all());
    }

    #[Test]
    public function a_supervisor_or_designated_employee_who_is_gone_falls_back_to_hr(): void
    {
        Employee::whereKey($this->ivan->employee_id)->update(['status' => 'terminated']);
        $noSupervisor = $this->userWithRole('employee', ['supervisor_id' => null]);

        $onboarding = $this->startFor($noSupervisor);
        $this->assertSame(['hr', null], [$this->task($onboarding, 'Meet supervisor')->assignee_type, $this->task($onboarding, 'Meet supervisor')->assignee_employee_id]);
        $this->assertSame(['hr', null], [$this->task($onboarding, 'Set up IT accounts')->assignee_type, $this->task($onboarding, 'Set up IT accounts')->assignee_employee_id]);
        $this->assertSame(1, $onboarding->events()->where('remarks', 'like', '%no current supervisor; assigned to HR%')->count());
    }

    #[Test]
    public function completion_needs_every_required_task_and_its_verification(): void
    {
        $onboarding = $this->startFor($this->nina);
        $complete = fn () => $this->actingAs($this->hr)->post(route('people.onboarding.complete', $onboarding));

        $complete()->assertSessionHasErrors(['status' => 'Onboarding cannot be completed because 4 required task(s) remain incomplete.']);

        Storage::fake('local');
        $tasks = app(OnboardingTaskService::class);
        $tasks->complete($this->task($onboarding, 'Submit government ID'), ['file' => UploadedFile::fake()->create('id.pdf', 20, 'application/pdf')], $this->nina);
        $tasks->complete($this->task($onboarding, 'Prepare workstation'), [], $this->hr);
        $tasks->complete($this->task($onboarding, 'Meet supervisor'), [], $this->sam);
        $tasks->complete($this->task($onboarding, 'Set up IT accounts'), [], $this->ivan);
        $complete()->assertSessionHasErrors(['status' => 'Onboarding cannot be completed because 1 required task(s) still need HR verification.']);

        $tasks->verify($this->task($onboarding, 'Submit government ID'), $this->hr);
        // The optional "Review company policies" is still pending: that's fine.
        $complete()->assertSessionHasNoErrors();

        $onboarding->refresh();
        $this->assertSame(['completed', $this->hr->id], [$onboarding->status, $onboarding->completed_by]);
        $this->assertSame('pending', $this->task($onboarding, 'Review company policies')->status);

        // Completed is final.
        $this->actingAs($this->hr)->post(route('people.onboarding.complete', $onboarding))->assertForbidden();
        $this->actingAs($this->hrManager)->post(route('people.onboarding.cancel', $onboarding), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->nina)->post(route('people.onboarding.tasks.complete', $this->task($onboarding, 'Review company policies')))->assertForbidden();
        try {
            app(OnboardingService::class)->complete($onboarding, $this->hr);
            $this->fail('completed twice');
        } catch (ValidationException) {
        }
        $this->expectException(LogicException::class);
        $onboarding->update(['completed_at' => now()->addDay()]);
    }

    #[Test]
    public function cancellation_needs_permission_and_a_reason_and_keeps_history(): void
    {
        $onboarding = $this->startFor($this->nina);
        app(OnboardingTaskService::class)->complete($this->task($onboarding, 'Prepare workstation'), [], $this->hr);

        $this->actingAs($this->hr)->post(route('people.onboarding.cancel', $onboarding), ['reason' => 'x'])->assertForbidden(); // HR staff: no onboarding.cancel
        $this->actingAs($this->hrManager)->post(route('people.onboarding.cancel', $onboarding), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->hrManager)->post(route('people.onboarding.cancel', $onboarding), ['reason' => 'Offer rescinded'])->assertSessionHasNoErrors();

        $onboarding->refresh();
        $this->assertSame(['cancelled', $this->hrManager->id, 'Offer rescinded'], [$onboarding->status, $onboarding->cancelled_by, $onboarding->cancellation_reason]);
        $this->assertSame(['Prepare workstation' => 'completed', 'Submit government ID' => 'cancelled', 'Meet supervisor' => 'cancelled', 'Set up IT accounts' => 'cancelled', 'Review company policies' => 'cancelled'],
            $onboarding->tasks->sortBy(fn ($t) => $t->status === 'completed' ? 0 : 1)->pluck('status', 'title')->all());
        $this->assertSame(1, $onboarding->events()->where('event', OnboardingEvent::CANCELLED)->count());

        $this->actingAs($this->sam)->post(route('people.onboarding.tasks.complete', $this->task($onboarding, 'Meet supervisor')))->assertForbidden();
        try {
            app(OnboardingTaskService::class)->complete($this->task($onboarding, 'Meet supervisor'), [], $this->hr);
            $this->fail('task completed after cancellation');
        } catch (ValidationException) {
        }
        // Nothing is deleted, and a new onboarding can start (no confirmation needed: none was completed).
        $this->assertSame(1, Onboarding::count());
        $this->startFor($this->nina, $onboarding->template);
        $this->assertSame(2, Onboarding::count());
    }

    #[Test]
    public function hr_lists_filters_and_sees_progress_and_overdue(): void
    {
        $nina = $this->startFor($this->nina);
        $ollie = $this->startFor($this->ollie, $this->template(['name' => 'Second']), ['start_date' => '2026-10-01']);
        app(OnboardingTaskService::class)->complete($this->task($nina, 'Prepare workstation'), [], $this->hr);

        $this->actingAs($this->hr)->get(route('people.onboarding.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('app/People/Onboarding/Index', false)
                ->has('onboardings.data', 2)
                ->where('stats.pending', 1)->where('stats.in_progress', 1)
                ->where('stats.overdue_tasks', 5) // Ollie started 2026-10-01: all 5 tasks were due by 2026-10-08
                ->where('onboardings.data.0.id', $ollie->id)->where('onboardings.data.0.overdue_count', 5)
                ->where('onboardings.data.1.tasks_total', 5)->where('onboardings.data.1.tasks_done', 1)->where('onboardings.data.1.required_total', 4)->where('onboardings.data.1.required_done', 1));

        $this->actingAs($this->hr)->get(route('people.onboarding.index', ['overdue' => 1]))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('onboardings.data', 1)->where('onboardings.data.0.id', $ollie->id));
        $this->actingAs($this->hr)->get(route('people.onboarding.index', ['search' => 'Nina']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('onboardings.data', 1)->where('onboardings.data.0.id', $nina->id));
        $this->actingAs($this->hr)->get(route('people.onboarding.index', ['status' => 'in_progress']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('onboardings.data', 1)->where('onboardings.data.0.id', $nina->id));
        $this->actingAs($this->hr)->get(route('people.onboarding.index', ['supervisor_id' => $this->sam->employee_id, 'start_from' => '2026-10-10']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('onboardings.data', 1)->where('onboardings.data.0.id', $nina->id));

        $this->actingAs($this->hr)->get(route('people.onboarding.show', $nina))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('progress.required_done', 1)->where('can.cancel', false)->where('can.complete', true)
                ->where('completionProblem', 'Onboarding cannot be completed because 3 required task(s) remain incomplete.'));
    }

    #[Test]
    public function hr_adds_extra_tasks_and_internal_notes(): void
    {
        $onboarding = $this->startFor($this->nina);

        $this->actingAs($this->hr)->post(route('people.onboarding.tasks.store', $onboarding), [
            'title' => 'Sign NDA', 'category' => 'first_day', 'assignee_type' => 'employee', 'due_date' => '2026-10-15',
            'is_required' => true, 'employee_visible' => true, 'requires_verification' => false,
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->hr)->post(route('people.onboarding.notes.store', $onboarding), ['body' => 'Laptop arrives Monday.'])->assertSessionHasNoErrors();

        $task = $this->task($onboarding, 'Sign NDA');
        $this->assertSame(['employee', $this->nina->employee_id, null], [$task->assignee_type, $task->assignee_employee_id, $task->onboarding_template_task_id]);
        $this->assertSame('Laptop arrives Monday.', $onboarding->notes()->value('body'));
        $this->assertSame(OnboardingTask::class, $task::class);
    }
}
