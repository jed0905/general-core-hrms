<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Employee onboarding (phase 6). Additive only.
 *
 * onboarding.* is HR management. onboarding.view_own / update_own are
 * self-service, like employee.view_own: they only ever reach the user's own
 * onboarding and tasks assigned to them (supervisor / designated employee),
 * enforced by policies, so every role that has self-service gets them.
 */
return new class extends Migration
{
    public const HR = [
        'onboarding.view',
        'onboarding.create',
        'onboarding.update',
        'onboarding.complete',
        'onboarding.cancel',
        'onboarding.task.update',
        'onboarding.task.verify',
        'onboarding.template.view',
        'onboarding.template.create',
        'onboarding.template.update',
    ];

    public const SELF = ['onboarding.view_own', 'onboarding.update_own'];

    public const GRANTS = [
        'superadmin' => [...self::HR, ...self::SELF],
        'hr_director' => [...self::HR, ...self::SELF],
        'hr_manager' => [...self::HR, ...self::SELF],
        // Operations; cancelling cases and editing templates stay with HR management.
        'hr_staff' => [
            'onboarding.view', 'onboarding.create', 'onboarding.update', 'onboarding.complete',
            'onboarding.task.update', 'onboarding.task.verify', 'onboarding.template.view',
            ...self::SELF,
        ],
        'supervisor' => self::SELF,
        'employee' => self::SELF,
        'payroll' => self::SELF,
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([...self::HR, ...self::SELF] as $name) {
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
        Permission::whereIn('name', [...self::HR, ...self::SELF])->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
