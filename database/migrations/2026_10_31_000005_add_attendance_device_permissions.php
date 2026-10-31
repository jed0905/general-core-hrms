<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Attendance module permissions added on top of the existing attendance.*
 * set (view, create, adjust, approve_adjustment, process, reprocess,
 * finalize, export, view_own). Additive only (never syncPermissions).
 *
 * attendance.scope.all: see every employee's attendance; without it a user
 * sees only their direct reports (supervisor_id) and themselves.
 * Device credentials need attendance.device.configure, separate from
 * registering/viewing devices. Keep in sync with RolesAndPermissionsSeeder.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'attendance.scope.all',
        'attendance.config.manage',
        'attendance.roster.manage',
        'attendance.device.view',
        'attendance.device.manage',
        'attendance.device.configure',
        'attendance.device.sync',
        'attendance.log.view',
        'attendance.log.import',
        'attendance.enrollment.view',
        'attendance.enrollment.manage',
    ];

    public const GRANTS = [
        'superadmin' => self::PERMISSIONS,
        'hr_director' => ['attendance.scope.all', 'attendance.config.manage', 'attendance.roster.manage', 'attendance.device.view', 'attendance.log.view', 'attendance.enrollment.view'],
        'hr_manager' => ['attendance.scope.all', 'attendance.roster.manage', 'attendance.device.view', 'attendance.device.manage', 'attendance.device.configure', 'attendance.device.sync', 'attendance.log.view', 'attendance.log.import', 'attendance.enrollment.view', 'attendance.enrollment.manage'],
        'hr_staff' => ['attendance.scope.all', 'attendance.roster.manage', 'attendance.device.view', 'attendance.device.sync', 'attendance.log.view', 'attendance.log.import', 'attendance.enrollment.view', 'attendance.enrollment.manage'],
        'payroll' => ['attendance.scope.all'],
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
