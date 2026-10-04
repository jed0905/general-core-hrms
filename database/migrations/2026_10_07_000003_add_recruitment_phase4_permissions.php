<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Recruitment phase 4: selection and offers. Additive only.
 *
 * Selection decisions and offer approval stay with HR management; HR staff
 * prepare, issue and record responses. Hiring managers see their own
 * vacancy's selections and offers through policies (assignment), and offer
 * approvers see the offers they are on; supervisors get nothing organization-wide.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'recruitment.selection.view',
        'recruitment.selection.create',
        'recruitment.offer.view',
        'recruitment.offer.create',
        'recruitment.offer.update',
        'recruitment.offer.approve',
        'recruitment.offer.issue',
        'recruitment.offer.respond',
        'recruitment.offer.withdraw',
    ];

    public const GRANTS = [
        'superadmin' => self::PERMISSIONS,
        'hr_director' => self::PERMISSIONS,
        'hr_manager' => self::PERMISSIONS,
        'hr_staff' => [
            'recruitment.selection.view',
            'recruitment.offer.view',
            'recruitment.offer.create',
            'recruitment.offer.update',
            'recruitment.offer.issue',
            'recruitment.offer.respond',
            'recruitment.offer.withdraw',
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
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
