<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions the movement module needs beyond the existing
 * employee_movement.{view,create,update,submit,approve,reject,implement,cancel,export}.
 * Only new permissions are granted; existing assignments are not touched.
 */
return new class extends Migration
{
    private array $grants = [
        'superadmin' => ['employee_movement.view_own', 'employee_movement_type.view', 'employee_movement_type.create', 'employee_movement_type.update', 'employee_movement_type.archive'],
        'hr_director' => ['employee_movement.view_own', 'employee_movement_type.view', 'employee_movement_type.create', 'employee_movement_type.update', 'employee_movement_type.archive'],
        'hr_manager' => ['employee_movement.view_own', 'employee_movement_type.view', 'employee_movement_type.create', 'employee_movement_type.update'],
        'hr_staff' => ['employee_movement.view_own', 'employee_movement_type.view'],
        'payroll' => ['employee_movement.view_own'],
        'supervisor' => ['employee_movement.view_own'],
        'employee' => ['employee_movement.view_own'],
    ];

    private function permissions(): array
    {
        return array_values(array_unique(array_merge(...array_values($this->grants))));
    }

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach ($this->grants as $roleName => $permissions) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', $this->permissions())->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
