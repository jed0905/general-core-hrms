<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * dashboard.hr.view decides who gets the organization-wide HR dashboard.
 * Everyone else gets the employee self-service dashboard. dashboard.view
 * can't make that distinction because supervisors and employees hold it.
 */
return new class extends Migration
{
    private string $permission = 'dashboard.hr.view';

    private array $roles = ['superadmin', 'hr_director', 'hr_manager', 'hr_staff'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => $this->permission, 'guard_name' => 'web']);

        foreach ($this->roles as $name) {
            Role::where('name', $name)->where('guard_name', 'web')->first()?->givePermissionTo($this->permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', $this->permission)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
