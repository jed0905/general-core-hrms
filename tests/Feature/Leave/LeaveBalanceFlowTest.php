<?php

namespace Tests\Feature\Leave;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApplication;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * available = balance - pending; approval moves pending -> used and subtracts from balance.
 */
class LeaveBalanceFlowTest extends LeaveTestCase
{
    private User $owner;

    private User $supervisor;

    private EmployeeLeaveBalance $balance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = $this->userWithRole('supervisor');
        $this->owner = $this->userWithRole('employee', ['supervisor_id' => $this->supervisor->employee_id]);
        $this->balance = $this->balanceFor($this->owner, balance: 10);
        $this->workflow();
    }

    private function file(array $overrides = []): LeaveApplication
    {
        $this->actingAs($this->owner)
            ->post(route('leave.applications.store'), $this->filingPayload($overrides))
            ->assertSessionHasNoErrors();

        return LeaveApplication::latest('id')->firstOrFail();
    }

    #[Test]
    public function submitting_reserves_pending_days(): void
    {
        $application = $this->file();

        $this->assertSame(LeaveApplication::STATUS_PENDING, $application->status);
        $this->assertEquals(2, (float) $application->total_days);
        $this->assertBalance($this->balance, 10, 2, 0);
        $this->assertSame(1, $application->approvals()->count());
        $this->assertSame(['pending'], $application->statusHistories()->pluck('status')->all());
    }

    #[Test]
    public function approving_moves_pending_to_used(): void
    {
        $application = $this->file();

        $this->actingAs($this->supervisor)
            ->post(route('leave.approvals.approve', $application), ['remarks' => 'Enjoy'])
            ->assertSessionHasNoErrors();

        $this->assertSame(LeaveApplication::STATUS_APPROVED, $application->fresh()->status);
        $this->assertBalance($this->balance, 8, 0, 2);
    }

    #[Test]
    public function rejecting_releases_pending(): void
    {
        $application = $this->file();

        $this->actingAs($this->supervisor)
            ->post(route('leave.approvals.reject', $application), ['remarks' => 'Busy season'])
            ->assertSessionHasNoErrors();

        $this->assertSame(LeaveApplication::STATUS_REJECTED, $application->fresh()->status);
        $this->assertBalance($this->balance, 10, 0, 0);
    }

    #[Test]
    public function reject_and_return_require_remarks(): void
    {
        $application = $this->file();

        foreach (['reject', 'return'] as $action) {
            $this->actingAs($this->supervisor)
                ->post(route("leave.approvals.{$action}", $application), ['remarks' => ''])
                ->assertSessionHasErrors('remarks');
        }

        $this->assertBalance($this->balance, 10, 2, 0);
    }

    #[Test]
    public function cancelling_pending_leave_releases_pending(): void
    {
        $application = $this->file();

        $this->actingAs($this->owner)->post(route('leave.applications.cancel', $application))->assertSessionHasNoErrors();

        $this->assertSame(LeaveApplication::STATUS_CANCELLED, $application->fresh()->status);
        $this->assertBalance($this->balance, 10, 0, 0);
        $this->assertSame(['skipped'], $application->approvals()->pluck('status')->all());
    }

    #[Test]
    public function cancelling_approved_leave_restores_used_and_balance(): void
    {
        $application = $this->file();
        $this->actingAs($this->supervisor)->post(route('leave.approvals.approve', $application));
        $this->assertBalance($this->balance, 8, 0, 2);

        $this->actingAs($this->userWithRole('hr_director'))
            ->post(route('leave.applications.cancel', $application))
            ->assertSessionHasNoErrors();

        $this->assertSame(LeaveApplication::STATUS_CANCELLED, $application->fresh()->status);
        $this->assertBalance($this->balance, 10, 0, 0);
    }

    #[Test]
    public function balance_is_not_deducted_twice(): void
    {
        $other = $this->balanceFor($this->userWithRole('employee'), balance: 5, pending: 3);
        $application = $this->file();

        // Cancelling twice: the second attempt is refused and changes nothing.
        $this->actingAs($this->owner)->post(route('leave.applications.cancel', $application));
        $this->actingAs($this->owner)->post(route('leave.applications.cancel', $application))->assertForbidden();
        $this->assertBalance($this->balance, 10, 0, 0);

        // The old bug: cancelling a rejected application subtracted pending again.
        $rejected = $this->file();
        $this->actingAs($this->supervisor)->post(route('leave.approvals.reject', $rejected), ['remarks' => 'No']);
        $this->balance->update(['pending' => 1]); // e.g. another open request
        $this->actingAs($this->userWithRole('hr_director'))->post(route('leave.applications.cancel', $rejected))->assertForbidden();
        $this->assertBalance($this->balance, 10, 1, 0);
        $this->balance->update(['pending' => 0]);

        // Return releases pending once; cancelling the returned request doesn't release it again.
        $returned = $this->file();
        $this->actingAs($this->supervisor)->post(route('leave.approvals.return', $returned), ['remarks' => 'Fix dates']);
        $this->assertBalance($this->balance, 10, 0, 0);
        $this->balance->update(['pending' => 1]); // another open request must stay reserved
        $this->actingAs($this->owner)->post(route('leave.applications.cancel', $returned))->assertSessionHasNoErrors();
        $this->assertBalance($this->balance, 10, 1, 0);
        $this->balance->update(['pending' => 0]);

        // Approving an already-approved application is refused.
        $approved = $this->file();
        $this->actingAs($this->supervisor)->post(route('leave.approvals.approve', $approved));
        $this->actingAs($this->supervisor)->post(route('leave.approvals.approve', $approved))->assertForbidden();
        $this->assertBalance($this->balance, 8, 0, 2);

        // Nobody else's balance moved.
        $this->assertBalance($other, 5, 3, 0);
    }

    #[Test]
    public function returned_application_can_be_corrected_and_resubmitted(): void
    {
        $application = $this->file();
        $this->actingAs($this->supervisor)->post(route('leave.approvals.return', $application), ['remarks' => 'One day only']);
        $this->assertSame(LeaveApplication::STATUS_RETURNED, $application->fresh()->status);

        $this->actingAs($this->owner)->put(route('leave.applications.update', $application), $this->filingPayload([
            'dates' => [['leave_date' => now()->addDays(20)->toDateString(), 'duration_type' => 'full_day']],
        ]))->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(LeaveApplication::STATUS_PENDING, $application->status);
        $this->assertEquals(1, (float) $application->total_days);
        $this->assertBalance($this->balance, 10, 1, 0);

        // The same approver picks it up again; approvals were not recreated.
        $this->assertSame(1, $application->approvals()->count());
        $this->actingAs($this->supervisor)->post(route('leave.approvals.approve', $application))->assertSessionHasNoErrors();
        $this->assertBalance($this->balance, 9, 0, 1);
        $this->assertSame(['pending', 'returned', 'pending', 'approved'], $application->statusHistories()->orderBy('id')->pluck('status')->all());
    }

    #[Test]
    public function editing_a_pending_application_replaces_its_reservation(): void
    {
        $application = $this->file();
        $this->assertBalance($this->balance, 10, 2, 0);

        // Same dates plus one more: must not collide with its own dates or count its own reservation.
        $this->actingAs($this->owner)->put(route('leave.applications.update', $application), $this->filingPayload([
            'dates' => [
                ['leave_date' => now()->addDays(14)->toDateString(), 'duration_type' => 'full_day'],
                ['leave_date' => now()->addDays(15)->toDateString(), 'duration_type' => 'full_day'],
                ['leave_date' => now()->addDays(16)->toDateString(), 'duration_type' => 'half_day'],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertBalance($this->balance, 10, 2.5, 0);
    }

    #[Test]
    public function filing_more_than_the_available_balance_is_refused(): void
    {
        $this->balance->update(['balance' => 3, 'pending' => 2]);

        $this->actingAs($this->owner)
            ->post(route('leave.applications.store'), $this->filingPayload())
            ->assertSessionHasErrors('leave_type_id');

        $this->assertSame(0, LeaveApplication::count());
        $this->assertBalance($this->balance, 3, 2, 0);
    }

    #[Test]
    public function leave_that_needs_no_approval_is_used_immediately(): void
    {
        $this->rule->update(['requires_approval' => false]);

        $application = $this->file();

        $this->assertSame(LeaveApplication::STATUS_APPROVED, $application->status);
        $this->assertSame(0, $application->approvals()->count());
        $this->assertBalance($this->balance, 8, 0, 2);
    }
}
