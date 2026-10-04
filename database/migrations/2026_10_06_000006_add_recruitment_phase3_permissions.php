<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Recruitment phase 3: interviews, assessments and evaluations. Additive only.
 *
 * Writing a scorecard needs no permission: it is granted by being an assigned
 * panelist of the interview (and only for one's own scorecard), so
 * supervisors and employees get nothing organization-wide here.
 * recruitment.evaluation.view is the organization-wide read of submitted scorecards.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'recruitment.interview.view',
        'recruitment.interview.create',
        'recruitment.interview.update',
        'recruitment.assessment.view',
        'recruitment.assessment.create',
        'recruitment.assessment.update',
        'recruitment.evaluation.view',
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
