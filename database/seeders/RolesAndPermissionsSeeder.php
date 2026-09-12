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
            // Dashboard
            'dashboard.view',

            // Employee Management
            'employee.view',
            'employee.create',
            'employee.update',
            'employee.archive',
            'employee.restore',
            'employee.export',
            'employee.import',

            'employee.personal.view',
            'employee.personal.update',

            'employee.employment.view',
            'employee.employment.update',

            'employee.compensation.view',
            'employee.compensation.update',

            'employee.documents.view',
            'employee.documents.create',
            'employee.documents.update',
            'employee.documents.delete',

            'employee.contacts.view',
            'employee.contacts.update',

            'employee.education.view',
            'employee.education.create',
            'employee.education.update',
            'employee.education.delete',

            'employee.experience.view',
            'employee.experience.create',
            'employee.experience.update',
            'employee.experience.delete',

            // Employee Self-Service
            'profile.view_own',
            'profile.update_own',

            'attendance.view_own',

            'leave.view_own',
            'leave.create',
            'leave.update_own',
            'leave.cancel_own',
            'leave.view_balance_own',
            'leave.view_history_own',

            'schedule.view_own',

            'request.view_own',
            'request.create',
            'request.cancel_own',

            // Employee Movements
            'employee_movement.view',
            'employee_movement.create',
            'employee_movement.update',
            'employee_movement.submit',
            'employee_movement.approve',
            'employee_movement.reject',
            'employee_movement.implement',
            'employee_movement.cancel',
            'employee_movement.export',

            // Organization
            'organization.view',
            'organization.create',
            'organization.update',
            'organization.archive',

            'department.view',
            'department.create',
            'department.update',
            'department.archive',

            // Work Shift
            'shift.view',
            'shift.create',
            'shift.update',
            'shift.archive',

            'work_schedule.view',
            'work_schedule.create',
            'work_schedule.update',
            'work_schedule.archive',

            'employee_work_schedule.view',
            'employee_work_schedule.assign',
            'employee_work_schedule.update',
            'employee_work_schedule.remove',

            // Attendance
            'attendance.view',
            'attendance.create',
            'attendance.update',
            'attendance.delete',
            'attendance.adjust',
            'attendance.approve_adjustment',
            'attendance.export',

            'attendance.process',
            'attendance.reprocess',
            'attendance.finalize',

            // Leave

            'leave_type.view',
            'leave_type.create',
            'leave_type.update',
            'leave_type.archive',
            
            'leave.view',
            'leave.create',
            'leave.update',
            'leave.delete',
            'leave.cancel',
            'leave.submit',
            'leave.export',

            // Leave Approval
            'leave.approval.view',
            'leave.approval.approve',
            'leave.approval.reject',
            'leave.approval.return',

            // Leave Balances
            'leave.balance.view',
            'leave.balance.create',
            'leave.balance.update',
            'leave.balance.adjust',
            'leave.balance.export',

            'leave.balance_history.view',
            'leave.balance_history.export',

            // Leave Policies
            'leave_policy.view',
            'leave_policy.create',
            'leave_policy.update',
            'leave_policy.archive',

            'leave_policy_rule.view',
            'leave_policy_rule.create',
            'leave_policy_rule.update',
            'leave_policy_rule.delete',

            // Holidays
            'holiday.view',
            'holiday.create',
            'holiday.update',
            'holiday.archive',
            'holiday.export',


            // Job Title Management
            'job_title.view',
            'job_title.create',
            'job_title.update',
            'job_title.archive',

            // Employment Status Management
            'employment_status.view',
            'employment_status.create',
            'employment_status.update',
            'employment_status.archive',

            // Role Management
            'role.view',
            'role.create',
            'role.update',
            'role.delete',
            'role.assign_permissions',
            'role.assign_users',

            // Permission Management
            'permission.view',

            // User Management
            'user.view',
            'user.create',
            'user.update',
            'user.deactivate',
            'user.activate',
            'user.reset_password',
            'user.assign_role',

            // System Administration
            'system.settings.view',
            'system.settings.update',

            'system.audit.view',

            'system.logs.view',

            'system.backup.view',
            'system.backup.create',

            'system.maintenance.enable',
            'system.maintenance.disable',

            // Company Settings
            'company.view',
            'company.update',

            'company_settings.view',
            'company_settings.update',

            // Reports
            'report.view',
            'report.export',
            'report.employee',
            'report.attendance',
            'report.leave',
        ];

        /*
        |--------------------------------------------------------------------------
        | Create Permissions
        |--------------------------------------------------------------------------
        */

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Default Roles
        |--------------------------------------------------------------------------
        |
        | These roles are provided as default templates.
        | Companies can modify the permissions assigned to each role
        | or create their own custom roles.
        |
        */

        $superadmin = Role::firstOrCreate([
            'name' => 'superadmin',
            'guard_name' => 'web',
        ]);

        $hrDirector = Role::firstOrCreate([
            'name' => 'hr_director',
            'guard_name' => 'web',
        ]);

        $hrManager = Role::firstOrCreate([
            'name' => 'hr_manager',
            'guard_name' => 'web',
        ]);

        $hrStaff = Role::firstOrCreate([
            'name' => 'hr_staff',
            'guard_name' => 'web',
        ]);

        $payroll = Role::firstOrCreate([
            'name' => 'payroll',
            'guard_name' => 'web',
        ]);

        $supervisor = Role::firstOrCreate([
            'name' => 'supervisor',
            'guard_name' => 'web',
        ]);

        $employee = Role::firstOrCreate([
            'name' => 'employee',
            'guard_name' => 'web',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Super Administrator
        |--------------------------------------------------------------------------
        */

        $superadmin->syncPermissions(
            Permission::where('guard_name', 'web')->get()
        );

        /*
        |--------------------------------------------------------------------------
        | HR Director
        |--------------------------------------------------------------------------
        */

        $hrDirector->syncPermissions([

            // Dashboard
            'dashboard.view',

            // Employees
            'employee.view',
            'employee.create',
            'employee.update',
            'employee.archive',
            'employee.restore',
            'employee.export',
            'employee.import',

            'employee.personal.view',
            'employee.personal.update',

            'employee.employment.view',
            'employee.employment.update',

            'employee.compensation.view',
            'employee.compensation.update',

            'employee.documents.view',
            'employee.documents.create',
            'employee.documents.update',
            'employee.documents.delete',

            'employee.contacts.view',
            'employee.contacts.update',

            'employee.education.view',
            'employee.education.create',
            'employee.education.update',
            'employee.education.delete',

            'employee.experience.view',
            'employee.experience.create',
            'employee.experience.update',
            'employee.experience.delete',

            // Employee Movements
            'employee_movement.view',
            'employee_movement.create',
            'employee_movement.update',
            'employee_movement.submit',
            'employee_movement.approve',
            'employee_movement.reject',
            'employee_movement.implement',
            'employee_movement.cancel',
            'employee_movement.export',

            // Organization
            'organization.view',
            'organization.create',
            'organization.update',
            'organization.archive',

            'department.view',
            'department.create',
            'department.update',
            'department.archive',

            // Work Shifts
            'shift.view',
            'shift.create',
            'shift.update',
            'shift.archive',

            'work_schedule.view',
            'work_schedule.create',
            'work_schedule.update',
            'work_schedule.archive',

            'employee_work_schedule.view',
            'employee_work_schedule.assign',
            'employee_work_schedule.update',
            'employee_work_schedule.remove',

            // Attendance
            'attendance.view',
            'attendance.create',
            'attendance.update',
            'attendance.delete',
            'attendance.adjust',
            'attendance.approve_adjustment',
            'attendance.export',
            'attendance.process',
            'attendance.reprocess',
            'attendance.finalize',

            // Leave
            'leave.view',
            'leave.create',
            'leave.update',
            'leave.delete',
            'leave.cancel',
            'leave.submit',
            'leave.export',

            // Leave Approval
            'leave.approval.view',
            'leave.approval.approve',
            'leave.approval.reject',
            'leave.approval.return',

            // Leave Balances
            'leave.balance.view',
            'leave.balance.create',
            'leave.balance.update',
            'leave.balance.adjust',
            'leave.balance.export',

            'leave.balance_history.view',
            'leave.balance_history.export',

            // Leave Policies
            'leave_policy.view',
            'leave_policy.create',
            'leave_policy.update',
            'leave_policy.archive',

            'leave_policy_rule.view',
            'leave_policy_rule.create',
            'leave_policy_rule.update',
            'leave_policy_rule.delete',

            // Holidays
            'holiday.view',
            'holiday.create',
            'holiday.update',
            'holiday.archive',
            'holiday.export',

            // Job Titles
            'job_title.view',
            'job_title.create',
            'job_title.update',
            'job_title.archive',

            // Roles
            'role.view',
            'role.create',
            'role.update',
            'role.delete',
            'role.assign_permissions',
            'role.assign_users',

            // Permissions
            'permission.view',

            // Users
            'user.view',
            'user.create',
            'user.update',
            'user.deactivate',
            'user.activate',
            'user.reset_password',
            'user.assign_role',

            // Company
            'company.view',
            'company.update',

            'company_settings.view',
            'company_settings.update',

            // System
            'system.settings.view',
            'system.settings.update',
            'system.audit.view',
            'system.logs.view',
        ]);

        /*
        |--------------------------------------------------------------------------
        | HR Manager
        |--------------------------------------------------------------------------
        */

        $hrManager->syncPermissions([

            // Dashboard
            'dashboard.view',

            // Employees
            'employee.view',
            'employee.create',
            'employee.update',
            'employee.archive',
            'employee.restore',
            'employee.export',

            'employee.personal.view',
            'employee.personal.update',

            'employee.employment.view',
            'employee.employment.update',

            'employee.documents.view',
            'employee.documents.create',
            'employee.documents.update',

            'employee.contacts.view',
            'employee.contacts.update',

            'employee.education.view',
            'employee.education.create',
            'employee.education.update',

            'employee.experience.view',
            'employee.experience.create',
            'employee.experience.update',

            // Employee Movements
            'employee_movement.view',
            'employee_movement.create',
            'employee_movement.update',
            'employee_movement.submit',
            'employee_movement.approve',
            'employee_movement.reject',
            'employee_movement.implement',
            'employee_movement.cancel',
            'employee_movement.export',

            // Organization
            'organization.view',

            'department.view',
            'department.create',
            'department.update',

            // Work Shifts
            'shift.view',
            'shift.create',
            'shift.update',

            'work_schedule.view',
            'work_schedule.create',
            'work_schedule.update',

            'employee_work_schedule.view',
            'employee_work_schedule.assign',
            'employee_work_schedule.update',
            'employee_work_schedule.remove',

            // Attendance
            'attendance.view',
            'attendance.create',
            'attendance.update',
            'attendance.adjust',
            'attendance.approve_adjustment',
            'attendance.export',
            'attendance.process',
            'attendance.reprocess',

            // Leave
            'leave.view',
            'leave.create',
            'leave.update',
            'leave.cancel',
            'leave.submit',
            'leave.export',

            // Leave Approval
            'leave.approval.view',
            'leave.approval.approve',
            'leave.approval.reject',
            'leave.approval.return',

            // Leave Balances
            'leave.balance.view',
            'leave.balance.create',
            'leave.balance.update',
            'leave.balance.adjust',
            'leave.balance.export',

            'leave.balance_history.view',
            'leave.balance_history.export',

            // Leave Policies
            'leave_policy.view',
            'leave_policy.create',
            'leave_policy.update',

            'leave_policy_rule.view',
            'leave_policy_rule.create',
            'leave_policy_rule.update',

            // Holidays
            'holiday.view',
            'holiday.create',
            'holiday.update',
            'holiday.archive',
            'holiday.export',

            // Job Titles
            'job_title.view',
            'job_title.create',
            'job_title.update',

            // Reports
            'report.view',
            'report.export',
            'report.employee',
            'report.attendance',
            'report.leave',
        ]);

        /*
        |--------------------------------------------------------------------------
        | HR Staff
        |--------------------------------------------------------------------------
        */

        $hrStaff->syncPermissions([

            // Dashboard
            'dashboard.view',

            // Employees
            'employee.view',
            'employee.create',
            'employee.update',
            'employee.export',

            'employee.personal.view',
            'employee.personal.update',

            'employee.employment.view',

            'employee.documents.view',
            'employee.documents.create',
            'employee.documents.update',

            'employee.contacts.view',
            'employee.contacts.update',

            'employee.education.view',
            'employee.education.create',
            'employee.education.update',

            'employee.experience.view',
            'employee.experience.create',
            'employee.experience.update',

            // Employee Movements
            'employee_movement.view',
            'employee_movement.create',
            'employee_movement.update',
            'employee_movement.submit',
            'employee_movement.export',

            // Organization
            'organization.view',
            'department.view',

            // Work Shifts
            'shift.view',
            'work_schedule.view',

            'employee_work_schedule.view',

            // Attendance
            'attendance.view',
            'attendance.create',
            'attendance.update',
            'attendance.adjust',
            'attendance.export',

            // Leave
            'leave.view',
            'leave.create',
            'leave.update',
            'leave.cancel',
            'leave.submit',
            'leave.export',

            // Leave Approval
            'leave.approval.view',

            // Leave Balances
            'leave.balance.view',
            'leave.balance.create',
            'leave.balance.update',
            'leave.balance.adjust',
            'leave.balance.export',

            'leave.balance_history.view',
            'leave.balance_history.export',

            // Leave Policies
            'leave_policy.view',
            'leave_policy_rule.view',

            // Holidays
            'holiday.view',

            // Job Titles
            'job_title.view',

            // Reports
            'report.view',
            'report.export',
            'report.employee',
            'report.attendance',
            'report.leave',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Payroll
        |--------------------------------------------------------------------------
        */

        // $payroll->syncPermissions([

        //     // Dashboard
        //     'dashboard.view',

        //     // Employee information required for payroll
        //     'employee.view',
        //     'employee.employment.view',
        //     'employee.compensation.view',

        //     // Attendance
        //     'attendance.view',
        //     'attendance.export',

        //     // Leave
        //     'leave.view',
        //     'leave.balance.view',
        //     'leave.balance_history.view',

        //     // Payroll
        //     'payroll.view',
        //     'payroll.create',
        //     'payroll.update',
        //     'payroll.calculate',
        //     'payroll.review',
        //     'payroll.approve',
        //     'payroll.reject',
        //     'payroll.finalize',
        //     'payroll.export',

        //     // Compensation
        //     'compensation.view',
        //     'compensation.create',
        //     'compensation.update',
        //     'compensation.approve',

        //     // Reports
        //     'report.view',
        //     'report.export',
        //     'report.employee',
        //     'report.attendance',
        //     'report.leave',
        // ]);

        /*
        |--------------------------------------------------------------------------
        | Supervisor
        |--------------------------------------------------------------------------
        |
        | Scope should later be applied at the application/business-logic
        | level so supervisors only see their direct reports.
        |
        */

        $supervisor->syncPermissions([

            // Dashboard
            'dashboard.view',

            // Employee information
            'employee.view',

            // Attendance
            'attendance.view',

            // Leave
            'leave.view',
            'leave.approval.view',
            'leave.approval.approve',
            'leave.approval.reject',
            'leave.approval.return',

            // Work Schedule
            'shift.view',
            'work_schedule.view',
            'employee_work_schedule.view',

            // Reports
            'report.view',
            'report.attendance',
            'report.leave',

            // Self Service
            'profile.view_own',
            'profile.update_own',

            'attendance.view_own',

            'leave.view_own',
            'leave.create',
            'leave.update_own',
            'leave.cancel_own',
            'leave.view_balance_own',
            'leave.view_history_own',

            'schedule.view_own',

            'request.view_own',
            'request.create',
            'request.cancel_own',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Employee
        |--------------------------------------------------------------------------
        */

        $employee->syncPermissions([

            // Dashboard
            'dashboard.view',

            // Self Service
            'profile.view_own',
            'profile.update_own',

            'attendance.view_own',

            'leave.view_own',
            'leave.create',
            'leave.update_own',
            'leave.cancel_own',
            'leave.view_balance_own',
            'leave.view_history_own',

            'schedule.view_own',

            'request.view_own',
            'request.create',
            'request.cancel_own',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Clear Permission Cache
        |--------------------------------------------------------------------------
        */

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
