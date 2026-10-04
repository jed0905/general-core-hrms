<?php

namespace Tests\Feature\Onboarding;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\OnboardingTask;
use App\Services\EmployeeDocumentService;
use App\Services\Onboarding\OnboardingTaskService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Task lifecycle and who may act: employee (own tasks), supervisor and
 * designated employee (assigned tasks), HR (any task, verification).
 */
class OnboardingTaskTest extends OnboardingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function act($user, OnboardingTask $task, string $action = 'complete', array $data = [])
    {
        return $this->actingAs($user)->post(route("people.onboarding.tasks.{$action}", $task), $data);
    }

    #[Test]
    public function the_lifecycle_moves_forward_only_and_records_actors(): void
    {
        $onboarding = $this->startFor($this->nina);
        $workstation = $this->task($onboarding, 'Prepare workstation');

        $this->act($this->hr, $workstation, 'start')->assertSessionHasNoErrors();
        $workstation->refresh();
        $this->assertSame(['in_progress', $this->hr->id], [$workstation->status, $workstation->started_by]);
        $this->assertSame('in_progress', $onboarding->fresh()->status, 'first task activity starts the onboarding');
        $this->act($this->hr, $workstation, 'start')->assertSessionHasErrors('status');

        $this->act($this->hr, $workstation, 'complete', ['remarks' => 'Desk 4B'])->assertSessionHasNoErrors();
        $workstation->refresh();
        $this->assertSame(['completed', $this->hr->id, 'Desk 4B'], [$workstation->status, $workstation->completed_by, $workstation->completion_remarks]);

        // Completed can't be completed again or restarted.
        $this->act($this->hr, $workstation)->assertForbidden();
        $this->act($this->hr, $workstation, 'start')->assertForbidden();
        try {
            app(OnboardingTaskService::class)->complete($workstation, [], $this->hrManager);
            $this->fail('completed twice');
        } catch (ValidationException) {
        }
        $this->assertSame($this->hr->id, $workstation->fresh()->completed_by, 'the original completer is kept');

        // pending → completed directly is allowed.
        $this->act($this->hr, $this->task($onboarding, 'Set up IT accounts'))->assertSessionHasNoErrors();

        $this->assertSame(['task_started', 'started', 'task_completed', 'task_completed'], $onboarding->events()->whereIn('event', ['task_started', 'started', 'task_completed'])->pluck('event')->all());
    }

    #[Test]
    public function required_tasks_can_never_be_skipped_and_optional_ones_need_hr(): void
    {
        $onboarding = $this->startFor($this->nina);
        $required = $this->task($onboarding, 'Prepare workstation');
        $optional = $this->task($onboarding, 'Review company policies');

        $this->act($this->hrManager, $required, 'skip', ['reason' => 'n/a'])->assertForbidden();
        try {
            app(OnboardingTaskService::class)->skip($required, 'n/a', $this->hrManager);
            $this->fail('required task skipped');
        } catch (ValidationException) {
        }

        $this->act($this->nina, $optional, 'skip', ['reason' => 'busy'])->assertForbidden();
        $this->act($this->hr, $optional, 'skip', [])->assertSessionHasErrors('reason');
        $this->act($this->hr, $optional, 'skip', ['reason' => 'Covered in orientation'])->assertSessionHasNoErrors();
        $optional->refresh();
        $this->assertSame(['skipped', $this->hr->id, 'Covered in orientation'], [$optional->status, $optional->skipped_by, $optional->skip_reason]);
        $this->act($this->nina, $optional)->assertForbidden();
    }

    #[Test]
    public function verification_is_separate_from_completion_and_by_someone_else(): void
    {
        $onboarding = $this->startFor($this->nina);
        $id = $this->task($onboarding, 'Submit government ID');

        $this->act($this->hr, $id, 'verify')->assertForbidden(); // not completed yet
        $this->act($this->nina, $id, 'complete', ['file' => UploadedFile::fake()->create('passport.pdf', 50, 'application/pdf')])->assertSessionHasNoErrors();

        $this->act($this->nina, $id, 'verify')->assertForbidden(); // employees can't verify
        $this->act($this->hr, $id, 'verify')->assertSessionHasNoErrors();
        $id->refresh();
        $this->assertSame([$this->nina->id, $this->hr->id], [$id->completed_by, $id->verified_by], 'completer kept, verifier added');
        $this->act($this->hr, $id, 'verify')->assertForbidden();

        $this->expectException(LogicException::class);
        $id->update(['completed_by' => $this->hr->id]);
    }

    #[Test]
    public function a_document_task_is_completed_with_a_private_document_of_the_required_type(): void
    {
        $onboarding = $this->startFor($this->nina);
        $task = $this->task($onboarding, 'Submit government ID');

        $this->act($this->nina, $task)->assertSessionHasErrors('file');
        $this->act($this->nina, $task, 'complete', ['employee_document_id' => 1])->assertSessionHasErrors('file'); // employees can't pick existing files
        $this->act($this->nina, $task, 'complete', ['file' => UploadedFile::fake()->create('id.exe', 5)])->assertSessionHasErrors('file');

        $this->act($this->nina, $task, 'complete', ['file' => UploadedFile::fake()->create('my-id.pdf', 50, 'application/pdf')])->assertSessionHasNoErrors();
        $document = EmployeeDocument::sole();
        $this->assertSame([$this->nina->employee_id, $this->idType, 'my-id.pdf', 'local', $this->nina->id], [$document->employee_id, $document->employee_document_type_id, $document->original_name, $document->disk, $document->uploaded_by]);
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertStringNotContainsString('my-id', $document->file_path);
        $this->assertSame($document->id, $task->fresh()->employee_document_id);

        // HR may complete with an existing document of the right type and employee only.
        $other = $this->startFor($this->ollie, $onboarding->template);
        $ollieTask = $this->task($other, 'Submit government ID');
        $this->act($this->hr, $ollieTask, 'complete', ['employee_document_id' => $document->id])->assertSessionHasErrors('file'); // Nina's document
        $ollieDoc = app(EmployeeDocumentService::class)->upload($other->employee, UploadedFile::fake()->create('ollie.pdf', 10, 'application/pdf'), ['employee_document_type_id' => $this->idType], $this->hr);
        $this->act($this->hr, $ollieTask, 'complete', ['employee_document_id' => $ollieDoc->id])->assertSessionHasNoErrors();
        $this->assertSame(2, EmployeeDocument::count(), 'no copy made');
    }

    #[Test]
    public function the_employee_sees_and_does_only_their_own_tasks(): void
    {
        $onboarding = $this->startFor($this->nina);
        $other = $this->startFor($this->ollie, $onboarding->template);

        $this->actingAs($this->nina)->get(route('people.my-onboarding.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('app/People/MyOnboarding/Index', false)
                ->where('onboarding.id', $onboarding->id)
                ->where('tasks', fn ($tasks) => collect($tasks)->pluck('title')->sort()->values()->all() === ['Meet supervisor', 'Review company policies', 'Set up IT accounts', 'Submit government ID'])
                ->where('tasks', fn ($tasks) => collect($tasks)->firstWhere('title', 'Meet supervisor')['can_act'] === false && collect($tasks)->firstWhere('title', 'Review company policies')['can_act'] === true)
                ->missing('notes')->missing('events')->has('assignedTasks', 0));

        // HR-only and other people's tasks are refused; other employees' onboarding is unreachable.
        $this->act($this->nina, $this->task($onboarding, 'Prepare workstation'))->assertForbidden();
        $this->act($this->nina, $this->task($onboarding, 'Meet supervisor'))->assertForbidden();
        $this->act($this->nina, $this->task($other, 'Review company policies'))->assertForbidden();
        $this->actingAs($this->nina)->get(route('people.onboarding.show', $onboarding))->assertForbidden();
        $this->actingAs($this->nina)->get(route('people.onboarding.index'))->assertForbidden();
        $this->actingAs($this->nina)->post(route('people.onboarding.complete', $onboarding))->assertForbidden();

        $this->act($this->nina, $this->task($onboarding, 'Review company policies'))->assertSessionHasNoErrors();
        $this->assertSame('completed', $this->task($onboarding, 'Review company policies')->status);
    }

    #[Test]
    public function supervisors_and_designated_employees_see_and_do_only_their_assigned_tasks(): void
    {
        $nina = $this->startFor($this->nina);
        $ollie = $this->startFor($this->ollie, $nina->template);

        $this->actingAs($this->sam)->get(route('people.my-onboarding.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('assignedTasks', 2)
                ->where('assignedTasks.0.title', 'Meet supervisor')->where('assignedTasks.0.can_act', true)
                ->where('onboarding', null));
        $this->actingAs($this->sam)->get(route('people.onboarding.index'))->assertForbidden();
        $this->actingAs($this->sam)->get(route('people.onboarding.show', $nina))->assertForbidden();

        $this->act($this->sam, $this->task($nina, 'Meet supervisor'))->assertSessionHasNoErrors();
        $this->act($this->sam, $this->task($nina, 'Prepare workstation'))->assertForbidden();
        $this->act($this->sam, $this->task($nina, 'Set up IT accounts'))->assertForbidden();
        $this->act($this->sam, $this->task($nina, 'Review company policies'))->assertForbidden();

        $this->act($this->ivan, $this->task($ollie, 'Set up IT accounts'))->assertSessionHasNoErrors();
        $this->act($this->ivan, $this->task($ollie, 'Meet supervisor'))->assertForbidden();

        // The snapshot decides: a new supervisor gains nothing on the existing case.
        $newBoss = $this->userWithRole('supervisor');
        Employee::whereKey($this->ollie->employee_id)->update(['supervisor_id' => $newBoss->employee_id]);
        $this->act($newBoss, $this->task($ollie, 'Meet supervisor'))->assertForbidden();
        $this->act($this->sam, $this->task($ollie, 'Meet supervisor'))->assertSessionHasNoErrors();
    }

    #[Test]
    public function hiring_managers_payroll_and_others_get_no_onboarding_authority(): void
    {
        $onboarding = $this->startFor($this->nina);
        $payroll = $this->userWithRole('payroll');

        foreach ([$payroll, $this->ollie] as $user) {
            $this->actingAs($user)->get(route('people.onboarding.index'))->assertForbidden();
            $this->actingAs($user)->get(route('people.onboarding.show', $onboarding))->assertForbidden();
            $this->act($user, $this->task($onboarding, 'Prepare workstation'))->assertForbidden();
            $this->act($user, $this->task($onboarding, 'Meet supervisor'))->assertForbidden();
            $this->actingAs($user)->post(route('people.onboarding.store'), ['employee_id' => $this->ollie->employee_id, 'onboarding_template_id' => $onboarding->onboarding_template_id, 'start_date' => '2026-10-15'])->assertForbidden();
        }
        // Payroll still has their own self-service page (empty).
        $this->actingAs($payroll)->get(route('people.my-onboarding.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('onboarding', null)->has('assignedTasks', 0));
    }
}
