<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // New Set of Permissions for existing roles
        // Define permissions
        $permissions = [
            // Administration
            'user.view',
            'user.create',
            'user.edit',
            'user.delete',
            'organization.view',
            'organization.manage',
            'role.view',
            'role.create',
            'role.edit',
            'role.delete',
            'permission.view',
            'permission.assign',

            // HR Management
            'job_structure.view',
            'job_structure.manage',
            'employee.view',
            'employee.create',
            'employee.edit',
            'employee.delete',
            'dtr.view',
            'dtr.print',
            'leave.view',
            'leave.apply',
            'leave.approve',
            'leave.recommend',
            'leave.assign_entitlement',

            // Self Service
            'profile.view',
            'profile.edit',
            'dtr.self.view',
            'dtr.self.print',
            'leave.self.view',
            'leave.self.apply',

            // Payroll
            'payroll.view',
            'payroll.process',
            'payroll.export',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Define roles
        $superadmin = Role::firstOrCreate(['name' => 'superadmin']);
        $ict = Role::firstOrCreate(['name' => 'ict']);
        $hrDirector = Role::firstOrCreate(['name' => 'hr_director']);
        $campusHr = Role::firstOrCreate(['name' => 'campus_hr']);
        $campusHrStaff = Role::firstOrCreate(['name' => 'campus_hr_staff']);
        $payroll = Role::firstOrCreate(['name' => 'payroll']);
        $college_secretary = Role::firstOrCreate(['name' => 'college_secretary']);
        $employee = Role::firstOrCreate(['name' => 'employee']);

        // Assign permissions
        $superadmin->givePermissionTo(Permission::all());

        $ict->givePermissionTo([
            // Administration
            'user.view',
            'user.create',
            'user.edit',
            'user.delete',
            'organization.view',
            'organization.manage',

            // HR Management
            'employee.view',

            // Self Service
            'profile.view',
            'profile.edit',
            'dtr.self.view',
            'dtr.self.print',
            'leave.self.view',
            'leave.self.apply',
        ]);

        $hrDirector->givePermissionTo([
            // Administration
            'organization.view',
            'organization.manage',

            // HR Management
            'job_structure.view',
            'job_structure.manage',
            'employee.view',
            'employee.create',
            'employee.edit',
            'employee.delete',
            'dtr.view',
            'dtr.print',
            'leave.view',
            'leave.apply',
            'leave.approve',
            'leave.assign_entitlement',

            // Self Service
            'profile.view',
            'profile.edit',
            'dtr.self.view',
            'dtr.self.print',
            'leave.self.view',
            'leave.self.apply',

        ]);

        $campusHr->givePermissionTo([
            // Administration
            'organization.view',
            'organization.manage',

            // HR Management
            'job_structure.view',
            'job_structure.manage',
            'employee.view',
            'employee.create',
            'employee.edit',
            'employee.delete',
            'dtr.view',
            'dtr.print',
            'leave.view',
            'leave.apply',
            'leave.approve',
            'leave.assign_entitlement',

            // Self Service
            'profile.view',
            'profile.edit',
            'dtr.self.view',
            'dtr.self.print',
            'leave.self.view',
            'leave.self.apply',
        ]);

        $campusHrStaff->givePermissionTo([
            // Administration
            'organization.view',
            'organization.manage',

            // HR Management
            'job_structure.view',
            'job_structure.manage',
            'employee.view',
            'employee.create',
            'employee.edit',
            'employee.delete',
            'dtr.view',
            'dtr.print',
            'leave.view',
            'leave.apply',
            'leave.approve',
            'leave.assign_entitlement',

            // Self Service
            'profile.view',
            'profile.edit',
            'dtr.self.view',
            'dtr.self.print',
            'leave.self.view',
            'leave.self.apply',
        ]);

        $payroll->givePermissionTo([
            // Payroll
            'payroll.view',
            'payroll.process',
            'payroll.export',

            // Self Service
            'profile.view',
            'profile.edit',
            'dtr.self.view',
            'dtr.self.print',
            'leave.self.view',
            'leave.self.apply',
        ]);

        $college_secretary->givePermissionTo([
            // HR Management
            'dtr.view',
            'dtr.print',

            // Self Service
            'profile.view',
            'profile.edit',
            'dtr.self.view',
            'dtr.self.print',
            'leave.self.view',
            'leave.self.apply',

        ]);

        $employee->givePermissionTo([
            // Self Service
            'profile.view',
            'profile.edit',
            'dtr.self.view',
            'dtr.self.print',
            'leave.self.view',
            'leave.self.apply',
        ]);
    }
}
