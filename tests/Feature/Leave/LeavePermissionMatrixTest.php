<?php

namespace Tests\Feature\Leave;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApprovalWorkflow;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyRule;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;

/**
 * Capability layer: every role against every permission-gated Leave endpoint,
 * using the roles exactly as RolesAndPermissionsSeeder creates them.
 */
class LeavePermissionMatrixTest extends LeaveTestCase
{
    private const ROLES = ['superadmin', 'hr_director', 'hr_manager', 'hr_staff', 'payroll', 'supervisor', 'employee'];

    private const ALL = self::ROLES;

    private const CONFIG_VIEW = ['superadmin', 'hr_director', 'hr_manager', 'hr_staff'];

    private const CONFIG_WRITE = ['superadmin', 'hr_director', 'hr_manager'];

    private const CONFIG_ARCHIVE = ['superadmin', 'hr_director'];

    public static function endpoints(): array
    {
        return [
            // Configuration
            'leave_type.view' => ['types.index', self::CONFIG_VIEW],
            'leave_type.create' => ['types.store', self::CONFIG_WRITE],
            'leave_type.update' => ['types.update', self::CONFIG_WRITE],
            'leave_type.archive' => ['types.destroy', self::CONFIG_ARCHIVE],
            'leave_policy.view' => ['policies.index', self::CONFIG_VIEW],
            'leave_policy.create' => ['policies.store', self::CONFIG_WRITE],
            'leave_policy.update' => ['policies.update', self::CONFIG_WRITE],
            'leave_policy.archive' => ['policies.destroy', self::CONFIG_ARCHIVE],
            'leave_policy_rule.view' => ['rules.index', self::CONFIG_VIEW],
            'leave_policy_rule.create' => ['rules.store', self::CONFIG_WRITE],
            'leave_policy_rule.update' => ['rules.update', self::CONFIG_WRITE],
            'leave_policy_rule.delete' => ['rules.destroy', self::CONFIG_ARCHIVE],
            'leave_approval_workflow.view' => ['workflows.index', self::CONFIG_VIEW],
            'leave_approval_workflow.create' => ['workflows.store', self::CONFIG_WRITE],
            'leave_approval_workflow.update' => ['workflows.update', self::CONFIG_WRITE],
            'leave_approval_workflow.archive' => ['workflows.destroy', self::CONFIG_ARCHIVE],

            // Balances
            'leave.balance.view' => ['balances.index', ['superadmin', 'hr_director', 'hr_manager', 'hr_staff', 'payroll']],
            'leave.balance.create' => ['balances.store', ['superadmin', 'hr_director', 'hr_manager', 'hr_staff']],
            'leave.balance.update' => ['balances.update', ['superadmin', 'hr_director']],
            'leave.balance.adjust' => ['balances.adjust', ['superadmin', 'hr_director', 'hr_manager']],

            // Approvals (capability only; record checks are covered elsewhere)
            'leave.approval.view' => ['approvals.index', ['superadmin', 'hr_director', 'hr_manager', 'supervisor']],

            // Self-service
            'leave.view_own' => ['applications.index', self::ALL],
            'leave.create' => ['applications.store', self::ALL],
            'leave.view_balance_own' => ['applications.my-balances', self::ALL],
            'leave.view_history_own' => ['applications.history', self::ALL],
        ];
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function endpoint_is_only_reachable_by_roles_holding_the_permission(string $endpoint, array $allowedRoles): void
    {
        foreach (self::ROLES as $role) {
            $user = $this->userWithRole($role);
            [$method, $url, $data] = $this->request($endpoint);

            $response = $this->actingAs($user)->{$method}($url, $data);

            if (in_array($role, $allowedRoles, true)) {
                $this->assertNotSame(403, $response->getStatusCode(), "{$role} should be allowed to {$endpoint}");
                $this->assertLessThan(500, $response->getStatusCode(), "{$role} got a server error on {$endpoint}");
            } else {
                $this->assertSame(403, $response->getStatusCode(), "{$role} should be denied {$endpoint}");
            }
        }
    }

    #[Test]
    public function seeded_roles_match_the_leave_permission_matrix(): void
    {
        $expected = [
            'hr_director' => ['leave_type.archive', 'leave_approval_workflow.archive', 'leave.balance.update', 'leave.cancel', 'leave.view_own'],
            'hr_manager' => ['leave_approval_workflow.update', 'leave.balance.adjust', 'leave.cancel', 'leave.approval.approve'],
            'hr_staff' => ['leave_approval_workflow.view', 'leave.view', 'leave.update', 'leave.balance.create'],
            'payroll' => ['leave.view', 'leave.balance.view', 'leave.balance_history.export', 'leave.view_own'],
            'supervisor' => ['leave.approval.approve', 'leave.approval.return', 'leave.view_own'],
            'employee' => ['leave.view_own', 'leave.create', 'leave.update_own', 'leave.cancel_own'],
        ];

        $forbidden = [
            'hr_director' => ['leave.delete', 'leave.submit'],
            'hr_manager' => ['leave.balance.update', 'leave_type.archive', 'leave_approval_workflow.archive'],
            'hr_staff' => ['leave.cancel', 'leave.balance.update', 'leave.balance.adjust', 'leave.approval.view', 'leave_approval_workflow.create'],
            'payroll' => ['leave.balance.create', 'leave.balance.adjust', 'leave.approval.view'],
            'supervisor' => ['leave.view', 'leave.balance.view'],
            'employee' => ['leave.view', 'leave.approval.view', 'leave.balance.view'],
        ];

        foreach ($expected as $role => $permissions) {
            foreach ($permissions as $permission) {
                $this->assertTrue(Role::findByName($role)->hasPermissionTo($permission), "{$role} should have {$permission}");
            }
        }

        foreach ($forbidden as $role => $permissions) {
            foreach ($permissions as $permission) {
                $this->assertFalse(Role::findByName($role)->hasPermissionTo($permission), "{$role} should not have {$permission}");
            }
        }
    }

    #[Test]
    public function every_role_can_file_its_own_leave(): void
    {
        foreach (self::ROLES as $role) {
            $this->assertTrue(Role::findByName($role)->hasPermissionTo('leave.create'), "{$role} cannot file leave");
            $this->assertTrue(Role::findByName($role)->hasPermissionTo('leave.view_own'), "{$role} cannot see own leave");
        }
    }

    #[Test]
    public function rerunning_the_seeder_keeps_custom_role_changes(): void
    {
        Role::findByName('hr_staff')->revokePermissionTo('leave.export');
        Role::findByName('employee')->givePermissionTo('leave.export');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertFalse(Role::findByName('hr_staff')->hasPermissionTo('leave.export'));
        $this->assertTrue(Role::findByName('employee')->hasPermissionTo('leave.export'));
    }

    #[Test]
    public function an_account_without_an_employee_record_cannot_file_leave(): void
    {
        $user = User::factory()->create()->assignRole('employee');

        $this->actingAs($user)->get(route('leave.applications.index'))->assertOk();
        $this->actingAs($user)->post(route('leave.applications.store'), $this->filingPayload())->assertForbidden();
    }

    /**
     * @return array{0: string, 1: string, 2: array}
     */
    private function request(string $endpoint): array
    {
        return match ($endpoint) {
            'types.index' => ['get', route('leave.config.types.index'), []],
            'types.store' => ['post', route('leave.config.types.store'), []],
            'types.update' => ['put', route('leave.config.types.update', LeaveType::factory()->create()), []],
            'types.destroy' => ['delete', route('leave.config.types.destroy', LeaveType::factory()->create()), []],
            'policies.index' => ['get', route('leave.config.policies.index'), []],
            'policies.store' => ['post', route('leave.config.policies.store'), []],
            'policies.update' => ['put', route('leave.config.policies.update', LeavePolicy::factory()->create()), []],
            'policies.destroy' => ['delete', route('leave.config.policies.destroy', LeavePolicy::factory()->create()), []],
            'rules.index' => ['get', route('leave.config.rules.index'), []],
            'rules.store' => ['post', route('leave.config.rules.store'), []],
            'rules.update' => ['put', route('leave.config.rules.update', LeavePolicyRule::factory()->create()), []],
            'rules.destroy' => ['delete', route('leave.config.rules.destroy', LeavePolicyRule::factory()->create()), []],
            'workflows.index' => ['get', route('leave.config.workflows.index'), []],
            'workflows.store' => ['post', route('leave.config.workflows.store'), []],
            'workflows.update' => ['put', route('leave.config.workflows.update', LeaveApprovalWorkflow::factory()->create(['is_active' => false])), []],
            'workflows.destroy' => ['delete', route('leave.config.workflows.destroy', LeaveApprovalWorkflow::factory()->create()), []],
            'balances.index' => ['get', route('leave.balances.index'), []],
            'balances.store' => ['post', route('leave.balances.store'), []],
            'balances.update' => ['put', route('leave.balances.update', EmployeeLeaveBalance::factory()->create(['leave_type_id' => $this->leaveType->id])), []],
            'balances.adjust' => ['post', route('leave.balances.adjust'), []],
            'approvals.index' => ['get', route('leave.approvals.index'), []],
            'applications.index' => ['get', route('leave.applications.index'), []],
            'applications.store' => ['post', route('leave.applications.store'), []],
            'applications.my-balances' => ['get', route('leave.applications.my-balances'), []],
            'applications.history' => ['get', route('leave.applications.history'), []],
        };
    }
}
