<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Recruitment phase 5: converting an applicant with an accepted offer into an
 * employee. Additive only. Converting also needs the existing Core HR
 * capabilities (employee.create, employee_movement.create, and user.create to
 * create a login), so no role gains Core HR powers it didn't already have.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'recruitment.conversion.view',
        'recruitment.conversion.create',
    ];

    public const GRANTS = [
        'superadmin' => self::PERMISSIONS,
        'hr_director' => self::PERMISSIONS,
        'hr_manager' => self::PERMISSIONS,
        'hr_staff' => self::PERMISSIONS,
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
