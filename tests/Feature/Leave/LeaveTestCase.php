<?php

namespace Tests\Feature\Leave;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveApprovalWorkflow;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyRule;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

abstract class LeaveTestCase extends TestCase
{
    use RefreshDatabase;

    protected LeaveType $leaveType;

    protected LeavePolicy $policy;

    protected LeavePolicyRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->leaveType = LeaveType::factory()->create(['name' => 'Vacation Leave', 'code' => 'VL']);
        $this->policy = LeavePolicy::factory()->create();
        $this->rule = LeavePolicyRule::factory()->create([
            'leave_policy_id' => $this->policy->id,
            'leave_type_id' => $this->leaveType->id,
        ]);
    }

    /**
     * RefreshDatabase wipes whatever it connects to. Refuse to run anywhere
     * except an in-memory SQLite database.
     */
    protected function beforeRefreshingDatabase()
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException("Leave tests only run against in-memory SQLite; got {$driver}:{$database}. Is the config cached? Run `php artisan config:clear`.");
        }
    }

    /**
     * A login linked to a new employee, holding one of the seeded roles.
     */
    protected function userWithRole(string $role, array $employeeAttributes = []): User
    {
        $employee = Employee::factory()->create($employeeAttributes);

        return User::factory()->create(['employee_id' => $employee->id])->assignRole($role);
    }

    protected function employeeOf(User $user): Employee
    {
        return Employee::findOrFail($user->employee_id);
    }

    protected function balanceFor(User|Employee $owner, float $balance = 10, float $pending = 0, float $used = 0): EmployeeLeaveBalance
    {
        $employeeId = $owner instanceof User ? $owner->employee_id : $owner->id;

        return EmployeeLeaveBalance::factory()->create([
            'employee_id' => $employeeId,
            'leave_type_id' => $this->leaveType->id,
            'balance' => $balance,
            'pending' => $pending,
            'used' => $used,
        ]);
    }

    /**
     * Company-wide workflow with the given step definitions.
     */
    protected function workflow(array $steps = [['approver_type' => 'immediate_supervisor']], array $attributes = []): LeaveApprovalWorkflow
    {
        $workflow = LeaveApprovalWorkflow::factory()->create($attributes);

        foreach (array_values($steps) as $index => $step) {
            $workflow->steps()->create(array_merge([
                'step_order' => $index + 1,
                'is_required' => true,
            ], $step));
        }

        return $workflow;
    }

    /**
     * An application with approval instances created directly, bypassing the resolver.
     *
     * @param  array<int, Employee>  $approvers  in approval order
     */
    protected function applicationFor(Employee $employee, array $approvers = [], string $status = LeaveApplication::STATUS_PENDING, float $days = 1): LeaveApplication
    {
        $application = LeaveApplication::factory()->status($status)->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'total_days' => $days,
            'total_hours' => $days * 8,
        ]);

        $application->dates()->create([
            'leave_date' => now()->addDays(10)->toDateString(),
            'duration_type' => 'full_day',
            'hours' => 8,
            'day_fraction' => 1,
        ]);

        foreach (array_values($approvers) as $index => $approver) {
            $application->approvals()->create([
                'approver_id' => $approver->id,
                'approver_name' => $approver->emp_first_name.' '.$approver->emp_last_name,
                'approver_type' => 'specific_employee',
                'approval_order' => $index + 1,
                'status' => LeaveApplicationApproval::STATUS_PENDING,
            ]);
        }

        return $application;
    }

    protected function filingPayload(array $overrides = []): array
    {
        return array_merge([
            'leave_type_id' => $this->leaveType->id,
            'reason' => 'Family trip',
            'dates' => [
                ['leave_date' => now()->addDays(14)->toDateString(), 'duration_type' => 'full_day'],
                ['leave_date' => now()->addDays(15)->toDateString(), 'duration_type' => 'full_day'],
            ],
        ], $overrides);
    }

    protected function assertBalance(EmployeeLeaveBalance $balance, float $expectedBalance, float $expectedPending, float $expectedUsed): void
    {
        $balance->refresh();

        $this->assertEqualsWithDelta($expectedBalance, (float) $balance->balance, 0.001, 'balance');
        $this->assertEqualsWithDelta($expectedPending, (float) $balance->pending, 0.001, 'pending');
        $this->assertEqualsWithDelta($expectedUsed, (float) $balance->used, 0.001, 'used');
    }
}
