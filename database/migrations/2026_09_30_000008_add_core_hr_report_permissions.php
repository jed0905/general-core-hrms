<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Category-level Core HR report permissions.
 *
 * New: report.work_schedule, report.user_access. Existing report.* permissions
 * are reused; grants only add what each role is missing and never revoke.
 */
return new class extends Migration
{
    private array $newPermissions = ['report.work_schedule', 'report.user_access'];

    private array $grants = [
        'superadmin' => ['report.work_schedule', 'report.user_access'],
        'hr_director' => ['report.view', 'report.export', 'report.employee', 'report.attendance', 'report.work_schedule', 'report.user_access'],
        'hr_manager' => ['report.work_schedule'],
        'hr_staff' => ['report.work_schedule'],
        // Payroll already holds report.leave; report.view opens the catalog.
        'payroll' => ['report.view'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->newPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach ($this->grants as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                continue;
            }

            foreach ($permissions as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }

            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Existing report permissions granted here (HR Director, payroll).
        $revoke = [
            'hr_director' => ['report.view', 'report.export', 'report.employee', 'report.attendance'],
            'payroll' => ['report.view'],
        ];

        foreach ($revoke as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            foreach ($permissions as $name) {
                if ($role && $role->hasPermissionTo($name)) {
                    $role->revokePermissionTo($name);
                }
            }
        }

        Permission::whereIn('name', $this->newPermissions)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
