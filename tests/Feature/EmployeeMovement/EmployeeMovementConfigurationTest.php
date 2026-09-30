<?php

namespace Tests\Feature\EmployeeMovement;

use App\Models\EmployeeMovementType;
use Database\Seeders\EmployeeMovementTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;

class EmployeeMovementConfigurationTest extends EmployeeMovementTestCase
{
    #[Test]
    public function movement_type_seeder_is_idempotent_and_keeps_admin_edits(): void
    {
        $this->assertSame(12, EmployeeMovementType::count());
        $this->assertSame(
            ['demotion', 'department_transfer', 'employment_status_change', 'hiring', 'job_title_change', 'other', 'promotion', 'reassignment', 'resignation', 'retirement', 'termination', 'transfer'],
            EmployeeMovementType::orderBy('code')->pluck('code')->all()
        );

        $this->type('promotion')->update(['name' => 'Career Advancement', 'is_active' => false]);
        EmployeeMovementType::create(['code' => 'secondment', 'name' => 'Secondment', 'affected_fields' => ['department_id']]);

        $this->seed(EmployeeMovementTypeSeeder::class);
        $this->seed(EmployeeMovementTypeSeeder::class);

        $this->assertSame(13, EmployeeMovementType::count(), 'no duplicates, custom type kept');
        $this->assertSame('Career Advancement', $this->type('promotion')->name, 'admin edit kept');
        $this->assertFalse($this->type('promotion')->is_active);
        $this->assertSame('terminated', $this->type('resignation')->employee_status);
    }

    #[Test]
    public function permission_seeder_is_idempotent_and_keeps_custom_assignments(): void
    {
        $snapshot = fn () => Role::orderBy('name')->get()->mapWithKeys(fn ($r) => [$r->name => $r->permissions()->pluck('name')->sort()->values()->all()])->all();

        Role::findByName('hr_staff')->givePermissionTo('employee_movement.cancel');
        Role::findByName('hr_manager')->revokePermissionTo('employee_movement.export');
        $before = $snapshot();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame($before, $snapshot(), 'rerunning changes nothing');
        $this->assertTrue(Role::findByName('hr_staff')->hasPermissionTo('employee_movement.cancel'));
        $this->assertFalse(Role::findByName('hr_manager')->hasPermissionTo('employee_movement.export'));
    }

    #[Test]
    public function seeded_movement_permissions_follow_the_matrix(): void
    {
        $expected = [
            'hr_director' => ['employee_movement.view', 'employee_movement.create', 'employee_movement.update', 'employee_movement.cancel', 'employee_movement.export', 'employee_movement_type.archive'],
            'hr_manager' => ['employee_movement.view', 'employee_movement.create', 'employee_movement.update', 'employee_movement.export', 'employee_movement_type.update'],
            'hr_staff' => ['employee_movement.view', 'employee_movement.create', 'employee_movement.update', 'employee_movement.export', 'employee_movement_type.view'],
        ];

        foreach ($expected as $role => $permissions) {
            foreach ($permissions as $permission) {
                $this->assertTrue(Role::findByName($role)->hasPermissionTo($permission), "{$role} lacks {$permission}");
            }
        }

        foreach (['supervisor', 'employee', 'payroll'] as $role) {
            $this->assertFalse(Role::findByName($role)->hasPermissionTo('employee_movement.view'), "{$role} has org-wide view");
            $this->assertTrue(Role::findByName($role)->hasPermissionTo('employee_movement.view_own'), "{$role} lacks view_own");
        }

        $this->assertFalse(Role::findByName('hr_staff')->hasPermissionTo('employee_movement.cancel'));
        $this->assertFalse(Role::findByName('hr_staff')->hasPermissionTo('employee_movement_type.create'));
    }

    #[Test]
    public function movement_types_are_configurable_by_permission(): void
    {
        $manager = $this->userWithRole('hr_manager');
        $staff = $this->userWithRole('hr_staff');

        $this->actingAs($manager)->post(route('people.employee-movement-types.store'), [
            'code' => 'secondment',
            'name' => 'Secondment',
            'affected_fields' => ['department_id', 'location_id'],
        ])->assertSessionHasNoErrors();

        $type = $this->type('secondment');
        $this->assertSame(['department_id', 'location_id'], $type->affected_fields);

        // codes are stable
        $this->actingAs($manager)->put(route('people.employee-movement-types.update', $type), ['code' => 'other_code', 'name' => 'Secondment'])
            ->assertSessionHasErrors('code');

        // bad values are refused
        $this->actingAs($manager)->post(route('people.employee-movement-types.store'), [
            'code' => 'Bad Code', 'name' => 'x', 'affected_fields' => ['salary'], 'employee_status' => 'resigned',
        ])->assertSessionHasErrors(['code', 'affected_fields.0', 'employee_status']);

        $this->actingAs($staff)->get(route('people.employee-movement-types.index'))->assertOk();
        $this->actingAs($staff)->post(route('people.employee-movement-types.store'), ['code' => 'x', 'name' => 'x'])->assertForbidden();
        $this->actingAs($manager)->delete(route('people.employee-movement-types.destroy', $type))->assertForbidden();

        $this->actingAs($this->userWithRole('hr_director'))->delete(route('people.employee-movement-types.destroy', $type))->assertRedirect();
        $this->assertFalse($type->fresh()->is_active);
    }
}
