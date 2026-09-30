<?php

namespace Tests\Feature\Leave;

use App\Models\LeaveApplication;
use App\Models\User;
use App\Services\Leave\LeaveApplicationService;
use App\Services\Leave\LeaveApprovalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Cancellation is authorized against the locked, current application row,
 * not the copy that was checked when the request came in.
 */
class LeaveCancellationTest extends LeaveTestCase
{
    private User $owner;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = $this->userWithRole('supervisor');
        $this->owner = $this->userWithRole('employee', ['supervisor_id' => $this->supervisor->employee_id]);
    }

    /**
     * Every balance also carries 1 day reserved by another open request, so a
     * double release or restoration would show up.
     *
     * status, actor, allowed, balance before [balance, pending, used], balance after
     */
    public static function cases(): array
    {
        return [
            'pending + owner' => [LeaveApplication::STATUS_PENDING, 'owner', true, [10, 3, 0], [10, 1, 0]],
            'returned + owner' => [LeaveApplication::STATUS_RETURNED, 'owner', true, [10, 1, 0], [10, 1, 0]],
            'pending + HR' => [LeaveApplication::STATUS_PENDING, 'hr', true, [10, 3, 0], [10, 1, 0]],
            'returned + HR' => [LeaveApplication::STATUS_RETURNED, 'hr', true, [10, 1, 0], [10, 1, 0]],
            'approved + owner' => [LeaveApplication::STATUS_APPROVED, 'owner', false, [8, 1, 2], [8, 1, 2]],
            'approved + HR' => [LeaveApplication::STATUS_APPROVED, 'hr', true, [8, 1, 2], [10, 1, 0]],
            'rejected + owner' => [LeaveApplication::STATUS_REJECTED, 'owner', false, [10, 1, 0], [10, 1, 0]],
            'rejected + HR' => [LeaveApplication::STATUS_REJECTED, 'hr', false, [10, 1, 0], [10, 1, 0]],
            'cancelled + owner' => [LeaveApplication::STATUS_CANCELLED, 'owner', false, [10, 1, 0], [10, 1, 0]],
            'cancelled + HR' => [LeaveApplication::STATUS_CANCELLED, 'hr', false, [10, 1, 0], [10, 1, 0]],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function cancellation_follows_status_and_actor(string $status, string $actor, bool $allowed, array $before, array $after): void
    {
        $balance = $this->balanceFor($this->owner, ...$before);
        $application = $this->applicationFor($this->employeeOf($this->owner), [$this->employeeOf($this->supervisor)], $status, days: 2);
        $user = $actor === 'owner' ? $this->owner : $this->userWithRole('hr_manager');

        $response = $this->actingAs($user)->post(route('leave.applications.cancel', $application));

        if ($allowed) {
            $response->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(LeaveApplication::STATUS_CANCELLED, $application->fresh()->status);
            $this->assertSame(1, $application->statusHistories()->where('status', LeaveApplication::STATUS_CANCELLED)->count());
        } else {
            $response->assertForbidden();
            $this->assertSame($status, $application->fresh()->status);
            $this->assertSame(0, $application->statusHistories()->count());
        }

        $this->assertBalance($balance, ...$after);
    }

    /**
     * Reproduces the race over HTTP: the route middleware authorizes the owner
     * while the application is still pending, then the supervisor's approval
     * commits before the cancellation transaction locks the row.
     */
    #[Test]
    public function owner_cancellation_fails_if_the_application_is_approved_after_the_initial_check(): void
    {
        $balance = $this->balanceFor($this->owner, 10, 2, 0);
        $application = $this->applicationFor($this->employeeOf($this->owner), [$this->employeeOf($this->supervisor)], days: 2);
        $this->approveRightAfterFirstCancelCheck($application);

        $this->actingAs($this->owner)
            ->post(route('leave.applications.cancel', $application))
            ->assertForbidden();

        $this->assertSame(LeaveApplication::STATUS_APPROVED, $application->fresh()->status);
        $this->assertBalance($balance, 8, 0, 2); // the approval's movement only; nothing restored
        $this->assertSame(0, $application->statusHistories()->where('status', LeaveApplication::STATUS_CANCELLED)->count());
    }

    #[Test]
    public function hr_cancellation_after_a_concurrent_approval_restores_the_used_days(): void
    {
        $hr = $this->userWithRole('hr_manager');
        $balance = $this->balanceFor($this->owner, 10, 2, 0);
        $application = $this->applicationFor($this->employeeOf($this->owner), [$this->employeeOf($this->supervisor)], days: 2);
        $this->approveRightAfterFirstCancelCheck($application);

        $this->actingAs($hr)
            ->post(route('leave.applications.cancel', $application))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Evaluated as an approved application: used days restored, pending untouched.
        $this->assertSame(LeaveApplication::STATUS_CANCELLED, $application->fresh()->status);
        $this->assertBalance($balance, 10, 0, 0);
    }

    #[Test]
    public function service_rechecks_authorization_against_the_locked_row_not_the_callers_copy(): void
    {
        $balance = $this->balanceFor($this->owner, 10, 2, 0);
        $application = $this->applicationFor($this->employeeOf($this->owner), [$this->employeeOf($this->supervisor)], days: 2);

        // The caller authorized this copy while it was pending...
        $staleCopy = LeaveApplication::findOrFail($application->id);
        $this->assertTrue($this->owner->can('cancel', $staleCopy));

        // ...then the approval commits.
        app(LeaveApprovalService::class)->approve($application, $this->supervisor->employee_id);
        $this->assertSame(LeaveApplication::STATUS_PENDING, $staleCopy->status, 'copy is stale');

        try {
            app(LeaveApplicationService::class)->cancelApplication($staleCopy, $this->owner);
            $this->fail('Owner cancelled an approved application.');
        } catch (AuthorizationException) {
            // expected
        }

        $this->assertSame(LeaveApplication::STATUS_APPROVED, $application->fresh()->status);
        $this->assertBalance($balance, 8, 0, 2);
    }

    #[Test]
    public function approval_after_a_committed_cancellation_is_refused(): void
    {
        $balance = $this->balanceFor($this->owner, 10, 2, 0);
        $application = $this->applicationFor($this->employeeOf($this->owner), [$this->employeeOf($this->supervisor)], days: 2);
        $staleCopy = LeaveApplication::findOrFail($application->id);

        app(LeaveApplicationService::class)->cancelApplication($application, $this->owner);

        $this->expectException(ValidationException::class);

        try {
            app(LeaveApprovalService::class)->approve($staleCopy, $this->supervisor->employee_id);
        } finally {
            $this->assertSame(LeaveApplication::STATUS_CANCELLED, $application->fresh()->status);
            $this->assertBalance($balance, 10, 0, 0);
        }
    }

    /**
     * Commits the supervisor's approval the moment the first `cancel` check
     * passes (the route middleware), i.e. between the request's authorization
     * and the service's locked transaction.
     */
    private function approveRightAfterFirstCancelCheck(LeaveApplication $application): void
    {
        $approved = false;

        Gate::after(function ($user, string $ability, $result) use ($application, &$approved) {
            if ($approved || $ability !== 'cancel' || $result !== true) {
                return;
            }

            $approved = true;
            app(LeaveApprovalService::class)->approve(LeaveApplication::findOrFail($application->id), $this->supervisor->employee_id);
        });
    }
}
