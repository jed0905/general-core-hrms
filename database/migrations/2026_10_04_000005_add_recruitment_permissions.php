<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Recruitment phase 1: job requisitions and vacancies.
 *
 * Grants only add what each role is missing and never revoke anything.
 * Supervisors get requisition actions that their policies limit to their own
 * requisitions and the approval steps assigned to them; they get no
 * organization-wide view. Hiring managers are assigned per vacancy, not by role.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'recruitment.requisition.view',
        'recruitment.requisition.create',
        'recruitment.requisition.update',
        'recruitment.requisition.submit',
        'recruitment.requisition.approve',
        'recruitment.requisition.cancel',
        'recruitment.vacancy.view',
        'recruitment.vacancy.create',
        'recruitment.vacancy.update',
        'recruitment.vacancy.publish',
        'recruitment.vacancy.close',
    ];

    public const GRANTS = [
        'superadmin' => self::PERMISSIONS,
        'hr_director' => self::PERMISSIONS,
        'hr_manager' => self::PERMISSIONS,
        'hr_staff' => [
            'recruitment.requisition.view',
            'recruitment.requisition.create',
            'recruitment.requisition.update',
            'recruitment.requisition.submit',
            'recruitment.requisition.cancel',
            'recruitment.vacancy.view',
            'recruitment.vacancy.create',
            'recruitment.vacancy.update',
            'recruitment.vacancy.publish',
        ],
        'supervisor' => [
            'recruitment.requisition.create',
            'recruitment.requisition.update',
            'recruitment.requisition.submit',
            'recruitment.requisition.cancel',
            'recruitment.requisition.approve',
        ],
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

        // Removing the permissions also detaches them from every role.
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
