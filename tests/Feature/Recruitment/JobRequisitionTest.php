<?php

namespace Tests\Feature\Recruitment;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\JobRequisition;
use App\Models\Vacancy;
use App\Services\Recruitment\RequisitionService;
use App\Services\Recruitment\VacancyService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;

class JobRequisitionTest extends RecruitmentTestCase
{
    // ----------------------------------------------------------- create / validate

    #[Test]
    public function a_supervisor_creates_a_draft_requisition_as_themselves(): void
    {
        $this->actingAs($this->ramon)
            ->post(route('recruitment.requisitions.store'), $this->requisitionPayload(['requested_by_employee_id' => $this->outsider->employee_id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $req = JobRequisition::sole();
        $this->assertSame(['REQ-2026-00001', 'draft', $this->ramon->employee_id, $this->ramon->id, 2],
            [$req->requisition_number, $req->status, $req->requested_by_employee_id, $req->created_by, $req->positions]);
    }

    #[Test]
    public function hr_can_raise_a_requisition_on_behalf_of_a_manager(): void
    {
        $this->actingAs($this->hrStaff)
            ->post(route('recruitment.requisitions.store'), $this->requisitionPayload(['requested_by_employee_id' => $this->ramon->employee_id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->ramon->employee_id, JobRequisition::sole()->requested_by_employee_id);
    }

    #[Test]
    public function requisitions_are_validated(): void
    {
        $this->actingAs($this->ramon)->post(route('recruitment.requisitions.store'), ['positions' => 0, 'reason' => 'expansion'])
            ->assertSessionHasErrors(['department_id', 'job_title_id', 'positions', 'reason', 'justification']);

        $this->actingAs($this->ramon)->post(route('recruitment.requisitions.store'), $this->requisitionPayload(['reason' => 'replacement']))
            ->assertSessionHasErrors('replaced_employee_id');

        $this->assertSame(0, JobRequisition::count());
    }

    #[Test]
    public function a_replaced_employee_is_only_kept_for_replacements(): void
    {
        $replacement = $this->draftRequisition(['reason' => 'replacement', 'replaced_employee_id' => $this->outsider->employee_id]);
        $newPosition = $this->draftRequisition(['reason' => 'new_position', 'replaced_employee_id' => $this->outsider->employee_id]);

        $this->assertSame($this->outsider->employee_id, $replacement->replaced_employee_id);
        $this->assertNull($newPosition->replaced_employee_id);
    }

    #[Test]
    public function numbers_are_unique_sequential_and_restart_each_year(): void
    {
        $this->assertSame(['REQ-2026-00001', 'REQ-2026-00002'], [$this->draftRequisition()->requisition_number, $this->draftRequisition()->requisition_number]);

        Carbon::setTestNow('2027-01-02 08:00:00');
        $this->assertSame('REQ-2027-00001', $this->draftRequisition()->requisition_number);
    }

    #[Test]
    public function users_without_requisition_permissions_cannot_create(): void
    {
        foreach (['employee', 'payroll'] as $role) {
            $this->actingAs($this->userWithRole($role))->post(route('recruitment.requisitions.store'), $this->requisitionPayload())->assertForbidden();
        }
        $this->assertSame(0, JobRequisition::count());
    }

    // ----------------------------------------------------------- submit / approval snapshot

    #[Test]
    public function submitting_snapshots_the_resolved_approval_chain(): void
    {
        $req = $this->draftRequisition();

        $this->actingAs($this->ramon)->post(route('recruitment.requisitions.submit', $req))->assertSessionHasNoErrors();

        $req->refresh();
        $this->assertSame('pending_approval', $req->status);
        $this->assertNotNull($req->submitted_at);
        $this->assertSame([
            [1, 'immediate_supervisor', true, $this->maya->employee_id, 'Maya Manager', 'pending', 'Standard Requisition Approval'],
            [2, 'higher_supervisor', false, $this->dina->employee_id, 'Dina Director', 'pending', 'Standard Requisition Approval'],
        ], $req->approvals->map(fn ($a) => [$a->approval_order, $a->approver_type, $a->is_required, $a->approver_id, $a->approver_name, $a->status, $a->workflow_name])->all());
    }

    #[Test]
    public function changing_the_workflow_or_org_chart_later_does_not_change_submitted_approvals(): void
    {
        $req = $this->submittedRequisition();
        $before = $req->approvals()->get(['approval_order', 'approver_id', 'approver_name', 'approver_type'])->toArray();

        $workflow = ApprovalWorkflow::where('module', 'job_requisition')->firstOrFail();
        $workflow->steps()->delete();
        $workflow->steps()->create(['step_order' => 1, 'approver_type' => ApprovalWorkflowStep::TYPE_SPECIFIC_EMPLOYEE, 'approver_employee_id' => $this->outsider->employee_id]);
        $workflow->update(['name' => 'Renamed']);
        $this->employee($this->ramon)->update(['supervisor_id' => $this->outsider->employee_id]);

        $this->assertSame($before, $req->approvals()->get(['approval_order', 'approver_id', 'approver_name', 'approver_type'])->toArray());
        $this->assertSame('Standard Requisition Approval', $req->approvals()->first()->workflow_name);

        // The original approvers still decide it.
        $this->actingAs($this->maya)->post(route('recruitment.requisitions.approve', $req))->assertSessionHasNoErrors();
        $this->actingAs($this->dina)->post(route('recruitment.requisitions.approve', $req))->assertSessionHasNoErrors();
        $this->assertSame('approved', $req->fresh()->status);

        // A new submission uses the new configuration.
        $later = $this->draftRequisition();
        $this->assertSame([$this->outsider->employee_id], app(RequisitionService::class)->submit($later, $this->ramon)->approvals->pluck('approver_id')->all());
    }

    #[Test]
    public function submission_fails_cleanly_without_a_configured_workflow_or_approver(): void
    {
        ApprovalWorkflow::query()->update(['is_active' => false]);
        $req = $this->draftRequisition();
        $this->actingAs($this->ramon)->post(route('recruitment.requisitions.submit', $req))->assertSessionHasErrors('approval');
        $this->assertSame('draft', $req->fresh()->status);

        ApprovalWorkflow::query()->update(['is_active' => true]);
        $orphan = $this->userWithRole('supervisor'); // no supervisor => required step 1 can't be assigned
        $orphanReq = $this->draftRequisition([], $orphan);
        $this->actingAs($orphan)->post(route('recruitment.requisitions.submit', $orphanReq))->assertSessionHasErrors('approval');
        $this->assertSame(0, $orphanReq->approvals()->count());
    }

    #[Test]
    public function a_requester_can_never_be_their_own_approver(): void
    {
        $workflow = ApprovalWorkflow::where('module', 'job_requisition')->firstOrFail();
        $workflow->steps()->delete();
        $workflow->steps()->create(['step_order' => 1, 'approver_type' => ApprovalWorkflowStep::TYPE_SPECIFIC_EMPLOYEE, 'approver_employee_id' => $this->ramon->employee_id]);

        $req = $this->draftRequisition();
        $this->actingAs($this->ramon)->post(route('recruitment.requisitions.submit', $req))->assertSessionHasErrors('approval');
        $this->assertSame('draft', $req->fresh()->status);
    }

    // ----------------------------------------------------------- approve / reject

    #[Test]
    public function approvals_are_sequential_and_only_the_current_approver_may_act(): void
    {
        $req = $this->submittedRequisition();

        // Step 2's approver cannot jump the queue; outsiders and HR without the step cannot act.
        $this->actingAs($this->dina)->post(route('recruitment.requisitions.approve', $req))->assertForbidden();
        $this->actingAs($this->outsider)->post(route('recruitment.requisitions.approve', $req))->assertForbidden();
        $this->actingAs($this->hrStaff)->post(route('recruitment.requisitions.approve', $req))->assertForbidden();

        $this->actingAs($this->maya)->post(route('recruitment.requisitions.approve', $req), ['remarks' => 'Budgeted.'])->assertSessionHasNoErrors();
        $this->assertSame('pending_approval', $req->fresh()->status);
        $this->actingAs($this->maya)->post(route('recruitment.requisitions.approve', $req))->assertForbidden(); // already acted

        $this->actingAs($this->dina)->post(route('recruitment.requisitions.approve', $req))->assertSessionHasNoErrors();

        $req->refresh();
        $this->assertSame('approved', $req->status);
        $this->assertNotNull($req->approved_at);
        $this->assertSame(['approved', 'approved'], $req->approvals->pluck('status')->all());
        $this->assertSame([$this->maya->id, 'Budgeted.'], [$req->approvals[0]->acted_by, $req->approvals[0]->remarks]);
    }

    #[Test]
    public function an_approver_who_loses_the_permission_can_no_longer_act(): void
    {
        $req = $this->submittedRequisition();
        $this->maya->removeRole('supervisor');

        $this->actingAs($this->maya->fresh())->post(route('recruitment.requisitions.approve', $req))->assertForbidden();
        $this->assertSame('pending', $req->approvals()->first()->status);
    }

    #[Test]
    public function rejection_needs_a_reason_and_skips_the_remaining_steps(): void
    {
        $req = $this->submittedRequisition();

        $this->actingAs($this->maya)->post(route('recruitment.requisitions.reject', $req))->assertSessionHasErrors('remarks');
        $this->actingAs($this->maya)->post(route('recruitment.requisitions.reject', $req), ['remarks' => 'Not in budget.'])->assertSessionHasNoErrors();

        $req->refresh();
        $this->assertSame('rejected', $req->status);
        $this->assertNotNull($req->rejected_at);
        $this->assertSame(['rejected', 'skipped'], $req->approvals->pluck('status')->all());
    }

    #[Test]
    public function rejected_requisitions_cannot_be_approved_resubmitted_or_edited(): void
    {
        $req = $this->submittedRequisition();
        $this->actingAs($this->maya)->post(route('recruitment.requisitions.reject', $req), ['remarks' => 'No.']);

        $this->actingAs($this->dina)->post(route('recruitment.requisitions.approve', $req))->assertForbidden();
        $this->actingAs($this->ramon)->post(route('recruitment.requisitions.submit', $req))->assertForbidden();
        $this->actingAs($this->ramon)->put(route('recruitment.requisitions.update', $req), $this->requisitionPayload())->assertForbidden();
        $this->assertSame('rejected', $req->fresh()->status);
    }

    #[Test]
    public function approved_and_pending_requisitions_cannot_be_resubmitted_or_edited(): void
    {
        $pending = $this->submittedRequisition();
        $approved = $this->approvedRequisition();

        foreach ([$pending, $approved] as $req) {
            $this->actingAs($this->ramon)->post(route('recruitment.requisitions.submit', $req))->assertForbidden();
            $this->actingAs($this->ramon)->put(route('recruitment.requisitions.update', $req), $this->requisitionPayload(['positions' => 9]))->assertForbidden();
        }

        // Even past the policy, the service re-checks state inside the transaction.
        $this->expectException(ValidationException::class);
        app(RequisitionService::class)->submit($approved, $this->ramon);
    }

    // ----------------------------------------------------------- cancel

    #[Test]
    public function cancellation_follows_the_allowed_states(): void
    {
        $draft = $this->draftRequisition();
        $this->actingAs($this->ramon)->post(route('recruitment.requisitions.cancel', $draft))->assertSessionHasErrors('reason');
        $this->actingAs($this->ramon)->post(route('recruitment.requisitions.cancel', $draft), ['reason' => 'Duplicate'])->assertSessionHasNoErrors();
        $this->assertSame(['cancelled', 'Duplicate', $this->ramon->id], [$draft->fresh()->status, $draft->fresh()->cancellation_reason, $draft->fresh()->cancelled_by]);

        $pending = $this->submittedRequisition();
        $this->actingAs($this->ramon)->post(route('recruitment.requisitions.cancel', $pending), ['reason' => 'Plans changed'])->assertSessionHasNoErrors();
        $this->assertSame(['skipped', 'skipped'], $pending->approvals()->pluck('status')->all());
        $this->actingAs($this->maya)->post(route('recruitment.requisitions.approve', $pending))->assertForbidden();

        // Cancelled and rejected requisitions stay final.
        $this->actingAs($this->ramon)->post(route('recruitment.requisitions.cancel', $pending), ['reason' => 'again'])->assertForbidden();
    }

    #[Test]
    public function an_approved_requisition_with_an_active_vacancy_cannot_be_cancelled(): void
    {
        $req = $this->approvedRequisition();
        $vacancy = app(VacancyService::class)->createVacancy($this->vacancyPayload(['job_requisition_id' => $req->id, 'openings' => 1]), $this->hrStaff);

        $this->actingAs($this->hrStaff)->post(route('recruitment.requisitions.cancel', $req), ['reason' => 'x'])->assertSessionHasErrors('status');
        $this->assertSame('approved', $req->fresh()->status);

        app(VacancyService::class)->transition($vacancy, Vacancy::STATUS_CANCELLED, $this->hrStaff);
        $this->actingAs($this->hrStaff)->post(route('recruitment.requisitions.cancel', $req), ['reason' => 'Hiring freeze'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $req->fresh()->status);
    }

    #[Test]
    public function others_cannot_cancel_or_edit_someone_elses_requisition(): void
    {
        $req = $this->draftRequisition();

        $this->actingAs($this->outsider)->post(route('recruitment.requisitions.cancel', $req), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->outsider)->put(route('recruitment.requisitions.update', $req), $this->requisitionPayload())->assertForbidden();
        $this->actingAs($this->outsider)->post(route('recruitment.requisitions.submit', $req))->assertForbidden();
    }

    // ----------------------------------------------------------- visibility

    #[Test]
    public function supervisors_only_see_their_own_and_assigned_requisitions(): void
    {
        $mine = $this->submittedRequisition();
        $other = $this->draftRequisition([], $this->outsider);

        $this->actingAs($this->maya)->get(route('recruitment.requisitions.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('requisitions.total', 1)->where('requisitions.data.0.id', $mine->id));
        $this->actingAs($this->maya)->get(route('recruitment.requisitions.show', $mine))->assertOk();
        $this->actingAs($this->maya)->get(route('recruitment.requisitions.show', $other))->assertForbidden();

        $this->actingAs($this->hrStaff)->get(route('recruitment.requisitions.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('requisitions.total', 2));

        $this->actingAs($this->userWithRole('employee'))->get(route('recruitment.requisitions.index'))->assertForbidden();
    }

    #[Test]
    public function the_detail_page_shows_the_approval_timeline_and_the_actions_allowed(): void
    {
        $req = $this->submittedRequisition();

        $this->actingAs($this->maya)->get(route('recruitment.requisitions.show', $req))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('app/Recruitment/Requisitions/Show', false)
                ->where('requisition.approvals.0.approver_name', 'Maya Manager')
                ->where('requisition.approvals.1.approver_name', 'Dina Director')
                ->where('can.approve', true)
                ->where('can.update', false));
    }
}
