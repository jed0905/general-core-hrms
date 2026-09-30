<?php

namespace Tests\Feature\Leave;

use App\Models\LeaveApplication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;

/**
 * Record layer: a capability permission never grants access to every record.
 */
class LeaveApplicationAuthorizationTest extends LeaveTestCase
{
    #[Test]
    public function employee_can_view_own_application(): void
    {
        $employee = $this->userWithRole('employee');
        $application = $this->applicationFor($this->employeeOf($employee));

        $this->actingAs($employee)
            ->get(route('leave.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('app/Leave/Applications/Show', false)
                ->where('application.id', $application->id)
                ->where('can.cancel', true)
                ->where('can.approve', false));
    }

    #[Test]
    public function employee_cannot_view_another_employees_application(): void
    {
        $employee = $this->userWithRole('employee');
        $other = $this->applicationFor($this->employeeOf($this->userWithRole('employee')));

        $this->actingAs($employee)->get(route('leave.applications.show', $other))->assertForbidden();
    }

    #[Test]
    public function assigned_approver_can_view_assigned_application(): void
    {
        $supervisor = $this->userWithRole('supervisor');
        $application = $this->applicationFor(
            $this->employeeOf($this->userWithRole('employee')),
            [$this->employeeOf($supervisor)]
        );

        $this->actingAs($supervisor)
            ->get(route('leave.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.approve', true));
    }

    #[Test]
    public function unassigned_supervisor_cannot_view_or_approve(): void
    {
        $assigned = $this->userWithRole('supervisor');
        $unassigned = $this->userWithRole('supervisor');
        $application = $this->applicationFor(
            $this->employeeOf($this->userWithRole('employee')),
            [$this->employeeOf($assigned)]
        );

        $this->actingAs($unassigned)->get(route('leave.applications.show', $application))->assertForbidden();
        $this->actingAs($unassigned)
            ->post(route('leave.approvals.approve', $application), ['remarks' => 'ok'])
            ->assertForbidden();

        $this->assertSame(LeaveApplication::STATUS_PENDING, $application->fresh()->status);
    }

    #[Test]
    public function approver_cannot_skip_the_approval_order(): void
    {
        $first = $this->userWithRole('supervisor');
        $second = $this->userWithRole('supervisor');
        $application = $this->applicationFor(
            $this->employeeOf($this->userWithRole('employee')),
            [$this->employeeOf($first), $this->employeeOf($second)]
        );

        foreach (['approve', 'reject', 'return'] as $action) {
            $this->actingAs($second)
                ->post(route("leave.approvals.{$action}", $application), ['remarks' => 'too early'])
                ->assertForbidden();
        }

        // The second approver doesn't see it in their queue until it's their turn.
        $this->actingAs($second)->get(route('leave.approvals.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('approvals.total', 0));

        $this->actingAs($first)->post(route('leave.approvals.approve', $application))->assertRedirect();

        $this->actingAs($second)->get(route('leave.approvals.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('approvals.total', 1)
                ->where('approvals.data.0.can.approve', true));
        $this->actingAs($second)->post(route('leave.approvals.approve', $application))->assertRedirect();

        $this->assertSame(LeaveApplication::STATUS_APPROVED, $application->fresh()->status);
    }

    #[Test]
    public function assigned_user_without_approval_permission_cannot_approve(): void
    {
        // Assigned on the record, but the employee role has no leave.approval.* capability.
        $employeeApprover = $this->userWithRole('employee');
        $application = $this->applicationFor(
            $this->employeeOf($this->userWithRole('employee')),
            [$this->employeeOf($employeeApprover)]
        );

        foreach (['approve', 'reject', 'return'] as $action) {
            $this->actingAs($employeeApprover)
                ->post(route("leave.approvals.{$action}", $application), ['remarks' => 'x'])
                ->assertForbidden();
        }

        $this->assertFalse($employeeApprover->can('approve', $application));
    }

    #[Test]
    public function permission_without_assignment_does_not_allow_approval(): void
    {
        // Superadmin holds every permission but is not an approver on this application.
        $superadmin = $this->userWithRole('superadmin');
        $application = $this->applicationFor(
            $this->employeeOf($this->userWithRole('employee')),
            [$this->employeeOf($this->userWithRole('supervisor'))]
        );

        $this->actingAs($superadmin)
            ->post(route('leave.approvals.approve', $application))
            ->assertForbidden();

        // ...but leave.view still lets them read it.
        $this->actingAs($superadmin)->get(route('leave.applications.show', $application))->assertOk();
    }

    #[Test]
    public function hr_users_with_leave_view_can_view_any_application(): void
    {
        $application = $this->applicationFor($this->employeeOf($this->userWithRole('employee')));

        foreach (['hr_director', 'hr_manager', 'hr_staff', 'payroll'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('leave.applications.show', $application))
                ->assertOk();
        }

        // Supervisors no longer hold organization-wide leave.view.
        $this->actingAs($this->userWithRole('supervisor'))
            ->get(route('leave.applications.show', $application))
            ->assertForbidden();
    }

    #[Test]
    public function attachments_are_private_and_policy_checked(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->userWithRole('employee');
        $supervisor = $this->userWithRole('supervisor');
        $this->employeeOf($owner)->update(['supervisor_id' => $supervisor->employee_id]);
        $this->workflow();
        $this->balanceFor($owner);

        $this->actingAs($owner)->post(route('leave.applications.store'), $this->filingPayload([
            'attachments' => [UploadedFile::fake()->create('medical.pdf', 20, 'application/pdf')],
        ]))->assertSessionHasNoErrors();

        $application = LeaveApplication::firstOrFail();
        $attachment = $application->attachments()->firstOrFail();

        Storage::disk('local')->assertExists($attachment->file_path);
        Storage::disk('public')->assertMissing($attachment->file_path);

        $url = route('leave.applications.attachments.download', [$application, $attachment]);

        $this->actingAs($owner)->get($url)->assertOk()->assertDownload('medical.pdf');
        $this->actingAs($supervisor)->get($url)->assertOk();
        $this->actingAs($this->userWithRole('hr_staff'))->get($url)->assertOk();
        $this->actingAs($this->userWithRole('employee'))->get($url)->assertForbidden();
        $this->actingAs($this->userWithRole('supervisor'))->get($url)->assertForbidden();
    }

    #[Test]
    public function attachment_must_belong_to_the_application_in_the_url(): void
    {
        Storage::fake('local');

        $owner = $this->userWithRole('employee');
        $mine = $this->applicationFor($this->employeeOf($owner));
        $theirs = $this->applicationFor($this->employeeOf($this->userWithRole('employee')));
        $theirAttachment = $theirs->attachments()->create([
            'file_path' => 'leave_attachments/secret.pdf',
            'file_name' => 'secret.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1,
            'uploaded_by' => $theirs->employee_id,
            'uploaded_at' => now(),
        ]);
        Storage::disk('local')->put('leave_attachments/secret.pdf', 'x');

        $this->actingAs($owner)
            ->get(route('leave.applications.attachments.download', [$mine, $theirAttachment]))
            ->assertNotFound();
    }

    #[Test]
    public function comments_follow_record_access_and_open_status(): void
    {
        $owner = $this->userWithRole('employee');
        $supervisor = $this->userWithRole('supervisor');
        $application = $this->applicationFor($this->employeeOf($owner), [$this->employeeOf($supervisor)]);
        $url = route('leave.applications.comments.store', $application);

        $this->actingAs($owner)->post($url, ['comment' => 'Dates are flexible'])->assertSessionHasNoErrors();
        $this->actingAs($supervisor)->post($url, ['comment' => 'Noted'])->assertSessionHasNoErrors();
        $this->actingAs($this->userWithRole('employee'))->post($url, ['comment' => 'Hi'])->assertForbidden();
        // HR can read (leave.view) but only the owner and approvers take part in the thread.
        $this->actingAs($this->userWithRole('hr_staff'))->post($url, ['comment' => 'Hi'])->assertForbidden();

        $this->assertSame(['Dates are flexible', 'Noted'], $application->comments()->orderBy('id')->pluck('comment')->all());

        $application->update(['status' => LeaveApplication::STATUS_APPROVED]);
        $this->actingAs($owner)->post($url, ['comment' => 'Thanks'])->assertForbidden();
    }

    #[Test]
    public function only_leave_cancel_holders_can_cancel_other_employees_leave(): void
    {
        $application = $this->applicationFor($this->employeeOf($this->userWithRole('employee')));

        $this->actingAs($this->userWithRole('hr_staff'))
            ->post(route('leave.applications.cancel', $application))
            ->assertForbidden();
        $this->actingAs($this->userWithRole('employee'))
            ->post(route('leave.applications.cancel', $application))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('hr_manager'))
            ->post(route('leave.applications.cancel', $application))
            ->assertRedirect();

        $this->assertSame(LeaveApplication::STATUS_CANCELLED, $application->fresh()->status);
    }

    #[Test]
    public function employee_cannot_edit_another_employees_application(): void
    {
        $application = $this->applicationFor($this->employeeOf($this->userWithRole('employee')));

        $this->actingAs($this->userWithRole('employee'))
            ->put(route('leave.applications.update', $application), $this->filingPayload())
            ->assertForbidden();
    }
}
