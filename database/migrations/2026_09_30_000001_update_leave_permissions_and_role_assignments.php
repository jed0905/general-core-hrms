<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings already-deployed roles in line with the Leave permission matrix.
 *
 * Only the listed role/permission pairs are touched, so any other
 * customization a company made to its roles is left alone. Roles that
 * don't exist yet are skipped; RolesAndPermissionsSeeder seeds them.
 */
return new class extends Migration
{
    private array $newPermissions = [
        'leave_approval_workflow.view',
        'leave_approval_workflow.create',
        'leave_approval_workflow.update',
        'leave_approval_workflow.archive',
    ];

    private array $grants = [
        'superadmin' => [
            'leave_approval_workflow.view',
            'leave_approval_workflow.create',
            'leave_approval_workflow.update',
            'leave_approval_workflow.archive',
        ],
        'hr_director' => [
            'leave_type.view',
            'leave_type.create',
            'leave_type.update',
            'leave_type.archive',
            'leave_approval_workflow.view',
            'leave_approval_workflow.create',
            'leave_approval_workflow.update',
            'leave_approval_workflow.archive',
            'leave.view_own',
            'leave.update_own',
            'leave.cancel_own',
            'leave.view_balance_own',
            'leave.view_history_own',
            'report.leave',
        ],
        'hr_manager' => [
            'leave_type.view',
            'leave_type.create',
            'leave_type.update',
            'leave_approval_workflow.view',
            'leave_approval_workflow.create',
            'leave_approval_workflow.update',
            'leave.view_own',
            'leave.update_own',
            'leave.cancel_own',
            'leave.view_balance_own',
            'leave.view_history_own',
        ],
        'hr_staff' => [
            'leave_type.view',
            'leave_approval_workflow.view',
            'leave.view_own',
            'leave.update_own',
            'leave.cancel_own',
            'leave.view_balance_own',
            'leave.view_history_own',
        ],
        'payroll' => [
            'leave.view_own',
            'leave.create',
            'leave.update_own',
            'leave.cancel_own',
            'leave.view_balance_own',
            'leave.view_history_own',
            'leave.view',
            'leave.export',
            'leave.balance.view',
            'leave.balance.export',
            'leave.balance_history.view',
            'leave.balance_history.export',
            'report.leave',
        ],
    ];

    private array $revokes = [
        'hr_director' => [
            'leave.delete',
            'leave.submit',
        ],
        'hr_manager' => [
            'leave.submit',
            'leave.balance.update',
        ],
        'hr_staff' => [
            'leave.cancel',
            'leave.submit',
            'leave.approval.view',
            'leave.balance.update',
            'leave.balance.adjust',
        ],
        'supervisor' => [
            // Grants organization-wide application access; supervisors see
            // applications through their assigned approval steps instead.
            'leave.view',
        ],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->newPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach ($this->grants as $roleName => $permissions) {
            $role = $this->findRole($roleName);

            if (! $role) {
                continue;
            }

            foreach ($permissions as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }

            $role->givePermissionTo($permissions);
        }

        foreach ($this->revokes as $roleName => $permissions) {
            $role = $this->findRole($roleName);

            if (! $role) {
                continue;
            }

            foreach ($permissions as $name) {
                if (Permission::where('name', $name)->where('guard_name', 'web')->exists() && $role->hasPermissionTo($name)) {
                    $role->revokePermissionTo($name);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->revokes as $roleName => $permissions) {
            $role = $this->findRole($roleName);

            if (! $role) {
                continue;
            }

            foreach ($permissions as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }

            $role->givePermissionTo($permissions);
        }

        foreach ($this->grants as $roleName => $permissions) {
            $role = $this->findRole($roleName);

            if (! $role) {
                continue;
            }

            foreach ($permissions as $name) {
                if (Permission::where('name', $name)->where('guard_name', 'web')->exists() && $role->hasPermissionTo($name)) {
                    $role->revokePermissionTo($name);
                }
            }
        }

        Permission::whereIn('name', $this->newPermissions)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function findRole(string $name): ?Role
    {
        return Role::where('name', $name)->where('guard_name', 'web')->first();
    }
};
