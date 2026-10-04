<?php

namespace Tests\Feature\Recruitment;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Employee;
use App\Models\JobOffer;
use App\Models\JobOfferApproval;
use App\Models\User;
use App\Services\Recruitment\OfferApprovalService;
use App\Services\Recruitment\OfferService;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Offer approvals reuse the generic approval workflow (module "job_offer") and
 * ApprovalChainResolver, snapshotted into job_offer_approvals.
 */
class OfferApprovalTest extends RecruitmentTestCase
{
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->hrApprovalChain();
    }

    private function submitted(): JobOffer
    {
        $app = $this->evaluated($this->openVacancy());
        $this->select($app);

        return app(OfferService::class)->submit($this->draftOffer($app), null, $this->hrStaff);
    }

    #[Test]
    public function the_chain_is_resolved_from_the_configured_workflow_and_snapshotted(): void
    {
        $offer = $this->submitted();

        $this->assertSame(
            [[1, 'immediate_supervisor', true, $this->manager->employee_id, 'Mona Manager', 'pending', 'Standard Offer Approval'], [2, 'higher_supervisor', false, $this->dina->employee_id, 'Dina Director', 'pending', 'Standard Offer Approval']],
            $offer->approvals->map(fn ($a) => [$a->approval_order, $a->approver_type, $a->is_required, $a->approver_id, $a->approver_name, $a->status, $a->workflow_name])->all()
        );
        $this->assertSame(ApprovalWorkflow::where('module', 'job_offer')->value('id'), $offer->approvals[0]->approval_workflow_id);
        $this->assertNotNull($offer->fresh()->submitted_at);
        $this->assertSame($this->hrStaff->id, $offer->fresh()->submitted_by);
    }

    #[Test]
    public function changing_the_workflow_later_never_changes_an_offers_approvals(): void
    {
        $offer = $this->submitted();
        $workflow = ApprovalWorkflow::where('module', 'job_offer')->first();

        $workflow->update(['name' => 'Finance Approval']);
        ApprovalWorkflowStep::where('approval_workflow_id', $workflow->id)->where('step_order', 1)
            ->update(['approver_type' => 'specific_employee', 'approver_employee_id' => $this->maya->employee_id]);
        ApprovalWorkflowStep::where('approval_workflow_id', $workflow->id)->where('step_order', 2)->delete();

        $offer->refresh();
        $this->assertSame([$this->manager->employee_id, $this->dina->employee_id], $offer->approvals->pluck('approver_id')->all());
        $this->assertSame(['Standard Offer Approval'], $offer->approvals->pluck('workflow_name')->unique()->values()->all());

        // The existing approval still completes under its snapshot.
        app(OfferApprovalService::class)->approve($offer, $this->manager);
        app(OfferApprovalService::class)->approve($offer, $this->dina);
        $this->assertSame('approved', $offer->fresh()->status);
    }

    #[Test]
    public function approvals_run_in_order_and_only_the_current_approver_acts_once(): void
    {
        $offer = $this->submitted();

        $this->actingAs($this->dina)->post(route('recruitment.offers.approve', $offer))->assertForbidden(); // step 2 before step 1
        $this->actingAs($this->hrStaff)->post(route('recruitment.offers.approve', $offer))->assertForbidden();
        $this->actingAs($this->maya)->post(route('recruitment.offers.approve', $offer))->assertForbidden();

        $this->actingAs($this->manager)->post(route('recruitment.offers.approve', $offer), ['remarks' => 'Fine'])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('recruitment.offers.approve', $offer))->assertForbidden(); // no duplicate approval

        $step = $offer->approvals()->where('approval_order', 1)->first();
        $this->assertSame(['approved', $this->manager->id, 'Fine'], [$step->status, $step->acted_by, $step->remarks]);
        $this->assertNotNull($step->acted_at);

        try {
            app(OfferApprovalService::class)->approve($offer, $this->manager);
            $this->fail('step approved twice');
        } catch (ValidationException) {
        }
        $this->assertSame(1, JobOfferApproval::where('job_offer_id', $offer->id)->where('status', 'approved')->count());
    }

    #[Test]
    public function no_approval_after_rejection_or_withdrawal(): void
    {
        $rejected = $this->submitted();
        app(OfferApprovalService::class)->reject($rejected, $this->manager, 'No budget');
        $withdrawn = $this->submitted();
        app(OfferService::class)->withdraw($withdrawn, 'Cancelled hire', $this->hrStaff);

        foreach ([$rejected, $withdrawn] as $offer) {
            $this->actingAs($this->manager)->post(route('recruitment.offers.approve', $offer))->assertForbidden();
            try {
                app(OfferApprovalService::class)->approve($offer, $this->dina);
                $this->fail('approved a closed offer');
            } catch (ValidationException) {
            }
            $this->actingAs($this->hrStaff)->post(route('recruitment.offers.issue', $offer))->assertForbidden();
        }
        $this->assertSame(['rejected', 'withdrawn'], [$rejected->fresh()->status, $withdrawn->fresh()->status]);
    }

    #[Test]
    public function the_submitter_and_creator_never_approve_their_own_offer(): void
    {
        // Make the HR manager both creator and submitter: the chain must skip them.
        $app = $this->evaluated($this->openVacancy());
        $this->select($app);
        $offer = app(OfferService::class)->createDraft($app, $this->offerTerms(), $this->manager);

        $offer = app(OfferService::class)->submit($offer, null, $this->manager);
        $this->assertSame([$this->dina->employee_id], $offer->approvals->pluck('approver_id')->all());

        // A submitter without a supervisor holding the permission can't start the chain.
        Employee::whereKey($this->hrStaff->employee_id)->update(['supervisor_id' => $this->maya->employee_id]); // supervisor without offer.approve
        $app2 = $this->evaluated($this->openVacancy());
        $this->select($app2);
        $draft = $this->draftOffer($app2);
        $this->actingAs($this->hrStaff)->post(route('recruitment.offers.submit', $draft))->assertSessionHasErrors('approval');
        $this->assertSame(['draft', 0], [$draft->fresh()->status, $draft->approvals()->count()]);
    }

    #[Test]
    public function an_unapproved_offer_can_never_be_issued(): void
    {
        $offer = $this->submitted();
        app(OfferApprovalService::class)->approve($offer, $this->manager); // one of two steps

        $this->actingAs($this->hrStaff)->post(route('recruitment.offers.issue', $offer))->assertForbidden();
        $this->expectException(ValidationException::class);
        app(OfferService::class)->issue($offer, null, $this->hrStaff);
    }

    // ------------------------------------------------------------- approver problems are named for HR

    /** Submit a fresh draft through the HR route; returns the "approval" error shown to HR. */
    private function approvalError(): string
    {
        $app = $this->evaluated($this->openVacancy());
        $this->select($app);
        $draft = $this->draftOffer($app);

        $response = $this->actingAs($this->hrStaff)->post(route('recruitment.offers.submit', $draft))->assertSessionHasErrors('approval');
        $this->assertSame(['draft', 0], [$draft->fresh()->status, $draft->approvals()->count()]);

        return session('errors')->first('approval');
    }

    #[Test]
    public function a_step_approver_without_a_user_account_is_named(): void
    {
        $this->manager->delete();

        $this->assertSame(
            'Approval step 1 (Immediate supervisor) cannot be assigned to Mona Manager. Mona Manager does not have an active user account. Please create a user account for Mona Manager before continuing.',
            $this->approvalError()
        );
    }

    #[Test]
    public function a_step_approver_with_an_inactive_account_is_named(): void
    {
        $this->manager->update(['status' => 'inactive']);

        $this->assertSame(
            "Approval step 1 (Immediate supervisor) cannot be assigned to Mona Manager. Mona Manager's user account is inactive. Please activate the account before continuing.",
            $this->approvalError()
        );
    }

    #[Test]
    public function a_step_approver_without_the_approval_permission_is_named(): void
    {
        $this->manager->syncRoles(['supervisor']);

        $this->assertSame(
            "Approval step 1 (Immediate supervisor) cannot be assigned to Mona Manager. Mona Manager's account does not have the required approval permission (recruitment.offer.approve). Please update the account's roles or permissions before continuing.",
            $this->approvalError()
        );
    }

    #[Test]
    public function a_step_with_no_resolvable_approver_says_so(): void
    {
        Employee::whereKey($this->hrStaff->employee_id)->update(['supervisor_id' => null]);

        $this->assertSame(
            'Approval step 1 (Immediate supervisor) cannot be assigned because no approver could be resolved from the configured approval workflow: Hana Staff has no supervisor assigned. Please review the approval workflow configuration.',
            $this->approvalError()
        );
    }

    #[Test]
    public function the_failing_step_number_is_reported_for_later_steps(): void
    {
        ApprovalWorkflowStep::where('approval_workflow_id', ApprovalWorkflow::where('module', 'job_offer')->value('id'))
            ->where('step_order', 2)->update(['is_required' => true]);
        $this->dina->update(['status' => 'inactive']);

        $this->assertSame(
            "Approval step 2 (Higher supervisor) cannot be assigned to Dina Director. Dina Director's user account is inactive. Please activate the account before continuing.",
            $this->approvalError()
        );
    }

    #[Test]
    public function the_detailed_approval_error_stays_inside_the_hr_workflow(): void
    {
        $this->manager->update(['status' => 'inactive']);
        $message = $this->approvalError();

        // No ids in the message: only the approver's name and what to fix.
        $this->assertStringNotContainsString((string) $this->manager->id, preg_replace('/step \d/', '', $message));

        // Offer submission is an HR route: guests (and therefore careers-portal candidates,
        // who never authenticate on the web guard) and employees without the permission can't reach it.
        $app = $this->evaluated($this->openVacancy());
        $this->select($app);
        $draft = $this->draftOffer($app);
        auth()->guard('web')->logout();
        $this->post(route('recruitment.offers.submit', $draft))->assertRedirect();
        $this->actingAs($this->maya)->post(route('recruitment.offers.submit', $draft))->assertForbidden();
        $this->assertSame('draft', $draft->fresh()->status);
    }
}
