<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Employee self-service permissions.
 *
 * The old self-service names are renamed in place so every role or user
 * that held them keeps the renamed permission, and no duplicate
 * permission exists afterwards:
 *   profile.view_own   -> employee.view_own
 *   profile.update_own -> employee.update_own
 *   schedule.view_own  -> work_schedule.view_own
 *
 * Grants are the difference from the previous seeded defaults only, so a
 * permission a company deliberately removed from a role isn't re-added.
 */
return new class extends Migration
{
    private array $renames = [
        'profile.view_own' => 'employee.view_own',
        'profile.update_own' => 'employee.update_own',
        'schedule.view_own' => 'work_schedule.view_own',
    ];

    private array $newPermissions = [
        'attendance.export_own',
    ];

    private array $grants = [
        'superadmin' => ['attendance.export_own'],
        'supervisor' => ['attendance.export_own'],
        'employee' => ['attendance.export_own'],
        'hr_director' => ['employee.view_own', 'employee.update_own', 'work_schedule.view_own', 'attendance.view_own', 'attendance.export_own'],
        'hr_manager' => ['employee.view_own', 'employee.update_own', 'work_schedule.view_own', 'attendance.view_own', 'attendance.export_own'],
        'hr_staff' => ['employee.view_own', 'employee.update_own', 'work_schedule.view_own', 'attendance.view_own', 'attendance.export_own'],
        'payroll' => ['employee.view_own', 'employee.update_own', 'work_schedule.view_own', 'attendance.view_own', 'attendance.export_own'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->renames as $from => $to) {
            $this->rename($from, $to);
        }

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

        foreach ($this->grants as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

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

        foreach ($this->renames as $from => $to) {
            $this->rename($to, $from);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Rename a permission. If both names exist, move every role/user
     * assignment onto the target and drop the source.
     */
    private function rename(string $from, string $to): void
    {
        $source = Permission::where('name', $from)->where('guard_name', 'web')->first();

        if (! $source) {
            return;
        }

        $target = Permission::where('name', $to)->where('guard_name', 'web')->first();

        if (! $target) {
            $source->update(['name' => $to]);

            return;
        }

        DB::transaction(function () use ($source, $target) {
            $tables = config('permission.table_names');
            $pivot = config('permission.column_names.permission_pivot_key') ?? 'permission_id';
            $rolePivot = config('permission.column_names.role_pivot_key') ?? 'role_id';

            foreach (DB::table($tables['role_has_permissions'])->where($pivot, $source->id)->get() as $row) {
                DB::table($tables['role_has_permissions'])->updateOrInsert(
                    [$pivot => $target->id, $rolePivot => $row->{$rolePivot}]
                );
            }

            foreach (DB::table($tables['model_has_permissions'])->where($pivot, $source->id)->get() as $row) {
                DB::table($tables['model_has_permissions'])->updateOrInsert([
                    $pivot => $target->id,
                    'model_type' => $row->model_type,
                    config('permission.column_names.model_morph_key') => $row->{config('permission.column_names.model_morph_key')},
                ]);
            }

            $source->delete();
        });
    }
};
