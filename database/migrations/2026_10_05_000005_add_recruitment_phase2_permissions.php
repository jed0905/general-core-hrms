<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Recruitment phase 2: applicants, applications, screening and shortlisting.
 * Additive only. Supervisors get nothing organization-wide; hiring managers see
 * applications for their own vacancies through policies (assignment).
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'recruitment.applicant.view',
        'recruitment.applicant.create',
        'recruitment.applicant.update',
        'recruitment.application.view',
        'recruitment.application.create',
        'recruitment.application.move_stage',
        'recruitment.application.reject',
        'recruitment.application.withdraw',
        'recruitment.screening.view',
        'recruitment.screening.create',
        'recruitment.screening.update',
        'recruitment.shortlist.create',
        'recruitment.config.manage',
    ];

    public const GRANTS = [
        'superadmin' => self::PERMISSIONS,
        'hr_director' => self::PERMISSIONS,
        'hr_manager' => self::PERMISSIONS,
        // Day-to-day recruiting; settings stay with HR management.
        'hr_staff' => [
            'recruitment.applicant.view',
            'recruitment.applicant.create',
            'recruitment.applicant.update',
            'recruitment.application.view',
            'recruitment.application.create',
            'recruitment.application.move_stage',
            'recruitment.application.reject',
            'recruitment.application.withdraw',
            'recruitment.screening.view',
            'recruitment.screening.create',
            'recruitment.screening.update',
            'recruitment.shortlist.create',
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
