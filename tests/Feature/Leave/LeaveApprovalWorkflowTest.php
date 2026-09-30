<?php

namespace Tests\Feature\Leave;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveApprovalWorkflow;
use App\Models\LeaveApprovalWorkflowStep;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

class LeaveApprovalWorkflowTest extends LeaveTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->userWithRole('hr_manager');
    }

    // ---------------------------------------------------------------
    // Configuration
    // ---------------------------------------------------------------

    #[Test]
    public function workflow_is_created_with_its_steps(): void
    {
        $approver = $this->userWithRole('hr_director');

        $this->actingAs($this->admin)->post(route('leave.config.workflows.store'), [
            'name' => 'Standard',
            'description' => 'Supervisor then HR',
            'leave_policy_id' => $this->policy->id,
            'is_active' => true,
            'steps' => [
                ['approver_type' => 'immediate_supervisor', 'is_required' => true],
                ['approver_type' => 'specific_employee', 'approver_employee_id' => $approver->employee_id, 'is_required' => false],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('leave.config.workflows.index'));

        $workflow = LeaveApprovalWorkflow::with('steps')->sole();
        $this->assertSame($this->policy->id, $workflow->leave_policy_id);
        $this->assertSame(
            [[1, 'immediate_supervisor', null, true], [2, 'specific_employee', $approver->employee_id, false]],
            $workflow->steps->map(fn ($s) => [$s->step_order, $s->approver_type, $s->approver_employee_id, $s->is_required])->all()
        );
    }

    #[Test]
    public function workflow_update_reorders_edits_adds_and_removes_steps(): void
    {
        $approver = $this->userWithRole('hr_director');
        $workflow = $this->workflow([
            ['approver_type' => 'immediate_supervisor'],
            ['approver_type' => 'higher_supervisor'],
            ['approver_type' => 'specific_employee', 'approver_employee_id' => $approver->employee_id],
        ]);
        [$immediate, $higher, $specific] = $workflow->steps->all();

        $this->actingAs($this->admin)->put(route('leave.config.workflows.update', $workflow), [
            'name' => 'Renamed',
            'is_active' => true,
            'steps' => [
                // swap the first two, make the specific step optional, drop nothing yet
                ['id' => $higher->id, 'approver_type' => 'higher_supervisor'],
                ['id' => $immediate->id, 'approver_type' => 'immediate_supervisor'],
                ['approver_type' => 'specific_employee', 'approver_employee_id' => $approver->employee_id, 'is_required' => false],
            ],
        ])->assertSessionHasNoErrors();

        $steps = $workflow->fresh()->steps;
        $this->assertSame('Renamed', $workflow->fresh()->name);
        $this->assertSame([$higher->id, $immediate->id], $steps->take(2)->pluck('id')->all(), 'existing rows are kept and reordered');
        $this->assertSame([1, 2, 3], $steps->pluck('step_order')->all());
        $this->assertFalse($steps[2]->is_required);
        $this->assertNull(LeaveApprovalWorkflowStep::find($specific->id), 'omitted step is removed');
    }

    #[Test]
    public function workflow_step_ids_from_another_workflow_are_rejected(): void
    {
        $mine = $this->workflow();
        $other = $this->workflow([['approver_type' => 'higher_supervisor']], ['is_active' => false]);

        $this->actingAs($this->admin)->put(route('leave.config.workflows.update', $mine), [
            'name' => 'Mine',
            'steps' => [['id' => $other->steps->first()->id, 'approver_type' => 'immediate_supervisor']],
        ])->assertSessionHasErrors('steps.0.id');
    }

    #[Test]
    public function workflow_is_archived_not_deleted(): void
    {
        $workflow = $this->workflow();

        $this->actingAs($this->userWithRole('hr_director'))
            ->delete(route('leave.config.workflows.destroy', $workflow))
            ->assertRedirect();

        $this->assertFalse($workflow->fresh()->is_active);
        $this->assertSame(1, $workflow->steps()->count());
    }

    #[Test]
    public function only_one_active_workflow_per_scope(): void
    {
        $this->workflow(); // company default

        $this->actingAs($this->admin)->post(route('leave.config.workflows.store'), [
            'name' => 'Second default',
            'steps' => [['approver_type' => 'immediate_supervisor']],
        ])->assertSessionHasErrors('leave_policy_id');

        // A policy-specific workflow is a different scope.
        $this->actingAs($this->admin)->post(route('leave.config.workflows.store'), [
            'name' => 'Policy workflow',
            'leave_policy_id' => $this->policy->id,
            'steps' => [['approver_type' => 'immediate_supervisor']],
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function unsupported_step_types_are_rejected(): void
    {
        foreach (['role', 'designation', 'department_head', 'hr'] as $type) {
            $this->actingAs($this->admin)->post(route('leave.config.workflows.store'), [
                'name' => "Uses {$type}",
                'steps' => [['approver_type' => $type]],
            ])->assertSessionHasErrors('steps.0.approver_type');
        }

        $this->assertSame(0, LeaveApprovalWorkflow::count());
    }

    #[Test]
    public function specific_approver_must_hold_approval_permission(): void
    {
        $plainEmployee = $this->userWithRole('employee');

        $this->actingAs($this->admin)->post(route('leave.config.workflows.store'), [
            'name' => 'Bad approver',
            'steps' => [['approver_type' => 'specific_employee', 'approver_employee_id' => $plainEmployee->employee_id]],
        ])->assertSessionHasErrors('steps.0.approver_employee_id');

        $this->assertSame(0, LeaveApprovalWorkflow::count());
    }

    #[Test]
    public function hr_staff_can_view_but_not_change_workflows(): void
    {
        $staff = $this->userWithRole('hr_staff');
        $workflow = $this->workflow();

        $this->actingAs($staff)->get(route('leave.config.workflows.index'))->assertOk();
        $this->actingAs($staff)->put(route('leave.config.workflows.update', $workflow), ['name' => 'x', 'steps' => []])->assertForbidden();
        $this->actingAs($staff)->delete(route('leave.config.workflows.destroy', $workflow))->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Approval instance creation at submission
    // ---------------------------------------------------------------

    #[Test]
    public function submission_resolves_steps_into_concrete_approvers(): void
    {
        $director = $this->userWithRole('hr_director');
        $manager = $this->userWithRole('hr_manager');
        $supervisor = $this->userWithRole('supervisor', ['supervisor_id' => $manager->employee_id]);
        $owner = $this->userWithRole('employee', ['supervisor_id' => $supervisor->employee_id]);
        $this->balanceFor($owner);
        $this->workflow([
            ['approver_type' => 'immediate_supervisor'],
            ['approver_type' => 'higher_supervisor'],
            ['approver_type' => 'specific_employee', 'approver_employee_id' => $director->employee_id],
        ]);

        $this->actingAs($owner)->post(route('leave.applications.store'), $this->filingPayload())->assertSessionHasNoErrors();

        $approvals = LeaveApplication::sole()->approvals()->orderBy('approval_order')->get();
        $this->assertSame(
            [
                [1, $supervisor->employee_id, 'immediate_supervisor', 'pending'],
                [2, $manager->employee_id, 'higher_supervisor', 'pending'],
                [3, $director->employee_id, 'specific_employee', 'pending'],
            ],
            $approvals->map(fn ($a) => [$a->approval_order, $a->approver_id, $a->approver_type, $a->status])->all()
        );
        $this->assertNotNull($approvals[0]->approver_name);
        $this->assertNotNull($approvals[0]->leave_approval_workflow_step_id);
    }

    #[Test]
    public function policy_specific_workflow_wins_over_the_company_default(): void
    {
        $director = $this->userWithRole('hr_director');
        $supervisor = $this->userWithRole('supervisor');
        $owner = $this->userWithRole('employee', ['supervisor_id' => $supervisor->employee_id]);
        $this->balanceFor($owner);
        $this->workflow([['approver_type' => 'immediate_supervisor']]);
        $policyWorkflow = $this->workflow(
            [['approver_type' => 'specific_employee', 'approver_employee_id' => $director->employee_id]],
            ['leave_policy_id' => $this->policy->id]
        );

        $this->actingAs($owner)->post(route('leave.applications.store'), $this->filingPayload())->assertSessionHasNoErrors();

        $approval = LeaveApplication::sole()->approvals()->sole();
        $this->assertSame($policyWorkflow->id, $approval->leave_approval_workflow_id);
        $this->assertSame($director->employee_id, $approval->approver_id);
    }

    #[Test]
    public function historical_approvals_do_not_change_when_the_workflow_changes(): void
    {
        $director = $this->userWithRole('hr_director');
        $supervisor = $this->userWithRole('supervisor');
        $owner = $this->userWithRole('employee', ['supervisor_id' => $supervisor->employee_id]);
        $this->balanceFor($owner);
        $workflow = $this->workflow([['approver_type' => 'immediate_supervisor']]);

        $this->actingAs($owner)->post(route('leave.applications.store'), $this->filingPayload())->assertSessionHasNoErrors();
        $first = LeaveApplication::sole();
        $snapshot = $first->approvals()->get(['approver_id', 'approver_type', 'approver_name', 'approval_order', 'status'])->toArray();

        // Replace the step entirely (the old step row is deleted).
        $this->actingAs($this->admin)->put(route('leave.config.workflows.update', $workflow), [
            'name' => $workflow->name,
            'steps' => [['approver_type' => 'specific_employee', 'approver_employee_id' => $director->employee_id]],
        ])->assertSessionHasNoErrors();

        // The existing application keeps its approver and snapshot; only the step link is cleared.
        $this->assertSame($snapshot, $first->approvals()->get(['approver_id', 'approver_type', 'approver_name', 'approval_order', 'status'])->toArray());
        $this->assertNull($first->approvals()->sole()->leave_approval_workflow_step_id);
        $this->actingAs($supervisor)->post(route('leave.approvals.approve', $first))->assertSessionHasNoErrors();
        $this->assertSame(LeaveApplication::STATUS_APPROVED, $first->fresh()->status);

        // New applications use the new configuration.
        $this->actingAs($owner)->post(route('leave.applications.store'), $this->filingPayload([
            'dates' => [['leave_date' => now()->addDays(30)->toDateString(), 'duration_type' => 'full_day']],
        ]))->assertSessionHasNoErrors();
        $second = LeaveApplication::latest('id')->first();
        $this->assertSame($director->employee_id, $second->approvals()->sole()->approver_id);
    }

    #[Test]
    public function required_step_that_cannot_be_resolved_blocks_submission(): void
    {
        $owner = $this->userWithRole('employee'); // no supervisor
        $balance = $this->balanceFor($owner);
        $this->workflow([['approver_type' => 'immediate_supervisor']]);

        $this->actingAs($owner)->post(route('leave.applications.store'), $this->filingPayload())->assertSessionHasErrors('approval');

        $this->assertSame(0, LeaveApplication::count());
        $this->assertSame(0, LeaveApplicationApproval::count());
        $this->assertBalance($balance, 10, 0, 0);
    }

    #[Test]
    public function optional_step_that_cannot_be_resolved_is_skipped(): void
    {
        $supervisor = $this->userWithRole('supervisor'); // has no supervisor of their own
        $owner = $this->userWithRole('employee', ['supervisor_id' => $supervisor->employee_id]);
        $this->balanceFor($owner);
        $this->workflow([
            ['approver_type' => 'immediate_supervisor'],
            ['approver_type' => 'higher_supervisor', 'is_required' => false],
        ]);

        $this->actingAs($owner)->post(route('leave.applications.store'), $this->filingPayload())->assertSessionHasNoErrors();

        $this->assertSame([$supervisor->employee_id], LeaveApplication::sole()->approvals()->pluck('approver_id')->all());
    }

    #[Test]
    public function approver_without_approval_permission_is_never_assigned(): void
    {
        // The supervisor_id points at someone whose login only has the employee role.
        $plainSupervisor = $this->userWithRole('employee');
        $owner = $this->userWithRole('employee', ['supervisor_id' => $plainSupervisor->employee_id]);
        $this->balanceFor($owner);
        $this->workflow([['approver_type' => 'immediate_supervisor']]);

        $this->actingAs($owner)->post(route('leave.applications.store'), $this->filingPayload())->assertSessionHasErrors('approval');
        $this->assertSame(0, LeaveApplicationApproval::count());

        // Deactivated accounts don't count either.
        $inactive = $this->userWithRole('supervisor');
        $inactive->update(['status' => 'inactive']);
        $this->employeeOf($owner)->update(['supervisor_id' => $inactive->employee_id]);

        $this->actingAs($owner)->post(route('leave.applications.store'), $this->filingPayload())->assertSessionHasErrors('approval');
        $this->assertSame(0, LeaveApplicationApproval::count());
    }

    #[Test]
    public function applicant_is_never_their_own_approver(): void
    {
        $director = $this->userWithRole('hr_director');
        EmployeeLeaveBalance::factory()->create(['employee_id' => $director->employee_id, 'leave_type_id' => $this->leaveType->id]);
        $this->workflow([['approver_type' => 'specific_employee', 'approver_employee_id' => $director->employee_id]]);

        $this->actingAs($director)->post(route('leave.applications.store'), $this->filingPayload())->assertSessionHasErrors('approval');
        $this->assertSame(0, LeaveApplication::count());
    }

    #[Test]
    public function submission_without_any_applicable_workflow_is_refused(): void
    {
        $owner = $this->userWithRole('employee');
        $this->balanceFor($owner);

        $this->actingAs($owner)->post(route('leave.applications.store'), $this->filingPayload())->assertSessionHasErrors('leave_type_id');
        $this->assertSame(0, LeaveApplication::count());
    }
}
