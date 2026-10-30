<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Global payroll permissions. Additive only (never syncPermissions).
 *
 * Separation of duties: the payroll role prepares, calculates and reviews; the
 * HR director approves, finalizes and verifies statutory rules. Policies also
 * stop one user from calculating and approving/finalizing the same run, and
 * from drafting and verifying the same rule version.
 * Entity scope: payroll.scope.all sees every legal entity; anyone else only the
 * entities assigned in payroll_entity_user. payslip.view_own is self-service.
 * Keep in sync with RolesAndPermissionsSeeder.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'payroll.dashboard.view',
        'payroll.config.view', 'payroll.config.manage',
        'compensation.view', 'compensation.create', 'compensation.update', 'compensation.approve',
        'payroll.view', 'payroll.create', 'payroll.update', 'payroll.calculate', 'payroll.review',
        'payroll.approve', 'payroll.reject', 'payroll.finalize', 'payroll.reverse', 'payroll.export',
        'payroll.disburse', 'payroll.input.manage', 'payroll.input.approve',
        'payroll.statutory_rule.view', 'payroll.statutory_rule.manage', 'payroll.statutory_rule.verify',
        'payroll.scope.all',
        'payslip.view', 'payslip.view_own',
        'report.payroll',
    ];

    public const GRANTS = [
        'superadmin' => self::PERMISSIONS,
        'hr_director' => [
            'payroll.dashboard.view', 'payroll.config.view', 'compensation.view', 'compensation.approve',
            'payroll.view', 'payroll.approve', 'payroll.reject', 'payroll.finalize', 'payroll.reverse',
            'payroll.export', 'payroll.statutory_rule.view', 'payroll.statutory_rule.verify',
            'payroll.scope.all', 'payslip.view', 'payslip.view_own', 'report.payroll',
        ],
        'hr_manager' => ['compensation.view', 'compensation.create', 'compensation.update', 'payslip.view_own'],
        'payroll' => [
            'payroll.dashboard.view', 'payroll.config.view', 'payroll.config.manage',
            'compensation.view', 'compensation.create', 'compensation.update',
            'payroll.view', 'payroll.create', 'payroll.update', 'payroll.calculate', 'payroll.review',
            'payroll.reject', 'payroll.export', 'payroll.disburse', 'payroll.input.manage', 'payroll.input.approve',
            'payroll.statutory_rule.view', 'payroll.statutory_rule.manage', 'payroll.scope.all',
            'payslip.view', 'payslip.view_own', 'report.payroll',
        ],
        'hr_staff' => ['payslip.view_own'],
        'supervisor' => ['payslip.view_own'],
        'employee' => ['payslip.view_own'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::GRANTS as $roleName => $permissions) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
