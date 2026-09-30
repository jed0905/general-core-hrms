<?php

namespace Tests\Feature\Leave;

use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * State layer: what each party may do in each application status.
 */
class LeaveApplicationStateTest extends LeaveTestCase
{
    private User $owner;

    private User $approver;

    private User $hrManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->userWithRole('employee');
        $this->approver = $this->userWithRole('supervisor');
        $this->hrManager = $this->userWithRole('hr_manager');
    }

    /**
     * status => [owner update, owner cancel, owner comment, approver approve/reject/return, HR (leave.cancel) cancel]
     */
    public static function states(): array
    {
        return [
            'pending' => [LeaveApplication::STATUS_PENDING, true, true, true, true, true],
            'returned' => [LeaveApplication::STATUS_RETURNED, true, true, true, false, true],
            'approved' => [LeaveApplication::STATUS_APPROVED, false, false, false, false, true],
            'rejected' => [LeaveApplication::STATUS_REJECTED, false, false, false, false, false],
            'cancelled' => [LeaveApplication::STATUS_CANCELLED, false, false, false, false, false],
        ];
    }

    #[Test]
    #[DataProvider('states')]
    public function abilities_follow_the_application_status(
        string $status,
        bool $ownerUpdate,
        bool $ownerCancel,
        bool $ownerComment,
        bool $approverActs,
        bool $hrCancel
    ): void {
        $application = $this->applicationFor($this->employeeOf($this->owner), [$this->employeeOf($this->approver)], $status);

        $this->assertSame($ownerUpdate, $this->owner->can('update', $application), 'owner update');
        $this->assertSame($ownerCancel, $this->owner->can('cancel', $application), 'owner cancel');
        $this->assertSame($ownerComment, $this->owner->can('comment', $application), 'owner comment');
        $this->assertTrue($this->owner->can('view', $application), 'owner view');
        $this->assertTrue($this->owner->can('viewAttachments', $application), 'owner attachments');

        foreach (['approve', 'reject', 'return'] as $ability) {
            $this->assertSame($approverActs, $this->approver->can($ability, $application), "approver {$ability}");
        }

        $this->assertSame($hrCancel, $this->hrManager->can('cancel', $application), 'HR cancel');
        $this->assertTrue($this->hrManager->can('view', $application), 'HR view');
    }

    #[Test]
    #[DataProvider('terminalStates')]
    public function terminal_applications_cannot_be_acted_on_over_http(string $status): void
    {
        $application = $this->applicationFor($this->employeeOf($this->owner), [$this->employeeOf($this->approver)], $status);

        $this->actingAs($this->owner)->post(route('leave.applications.cancel', $application))->assertForbidden();
        $this->actingAs($this->hrManager)->post(route('leave.applications.cancel', $application))->assertForbidden();
        $this->actingAs($this->owner)->put(route('leave.applications.update', $application), $this->filingPayload())->assertForbidden();
        $this->actingAs($this->approver)->post(route('leave.approvals.approve', $application))->assertForbidden();

        $this->assertSame($status, $application->fresh()->status);
    }

    public static function terminalStates(): array
    {
        return [
            'rejected' => [LeaveApplication::STATUS_REJECTED],
            'cancelled' => [LeaveApplication::STATUS_CANCELLED],
        ];
    }

    #[Test]
    public function pending_application_cannot_be_edited_once_an_approver_signed_off(): void
    {
        $second = $this->userWithRole('supervisor');
        $application = $this->applicationFor(
            $this->employeeOf($this->owner),
            [$this->employeeOf($this->approver), $this->employeeOf($second)]
        );

        $this->assertTrue($this->owner->can('update', $application));

        $application->approvals()->where('approval_order', 1)->update(['status' => LeaveApplicationApproval::STATUS_APPROVED]);

        $this->assertFalse($this->owner->can('update', $application->fresh()));
        $this->assertTrue($this->owner->can('cancel', $application->fresh()));
    }

    #[Test]
    public function owner_cannot_cancel_approved_leave_but_hr_can(): void
    {
        $application = $this->applicationFor($this->employeeOf($this->owner), [], LeaveApplication::STATUS_APPROVED);

        $this->actingAs($this->owner)->post(route('leave.applications.cancel', $application))->assertForbidden();
        $this->actingAs($this->hrManager)->post(route('leave.applications.cancel', $application))->assertRedirect();

        $this->assertSame(LeaveApplication::STATUS_CANCELLED, $application->fresh()->status);
    }
}
