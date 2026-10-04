<?php

namespace Tests\Feature\People;

use App\Models\Employee;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * Employee create/update validation moved from the controller into
 * StoreEmployeeRequest / UpdateEmployeeRequest; behaviour is unchanged.
 */
class EmployeeFormRequestTest extends LeaveTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'employee_number' => 'T-0100',
            'emp_first_name' => 'Juan',
            'emp_last_name' => 'Dela Cruz',
            'emp_sex' => 'male',
            'status' => 'active',
        ], $overrides);
    }

    #[Test]
    public function hr_can_create_and_the_existing_fields_are_saved(): void
    {
        $this->actingAs($this->userWithRole('hr_staff'))
            ->post(route('people.employee.store'), $this->payload([
                'work_email' => 'juan@example.test',
                'joined_date' => '2026-10-01',
                'education' => [['level' => 'Bachelor', 'institute' => 'State College', 'year' => 2018]],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $employee = Employee::where('employee_number', 'T-0100')->firstOrFail();
        $this->assertSame(['Juan', 'juan@example.test', '2026-10-01'], [$employee->emp_first_name, $employee->work_email, substr($employee->joined_date, 0, 10)]);
        $this->assertSame('State College', $employee->education()->sole()->institute);
    }

    #[Test]
    public function creating_and_updating_require_the_employee_permissions(): void
    {
        $target = Employee::factory()->create();

        foreach (['supervisor', 'employee', 'payroll'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->post(route('people.employee.store'), $this->payload())->assertForbidden();
            $this->actingAs($user)->put(route('people.employee.update', $target), $this->payload())->assertForbidden();
        }

        $this->assertDatabaseMissing('employees', ['employee_number' => 'T-0100']);
    }

    #[Test]
    public function required_fields_and_unique_values_are_still_enforced(): void
    {
        $hr = $this->userWithRole('hr_manager');
        $existing = Employee::factory()->create(['employee_number' => 'T-0001', 'work_email' => 'taken@example.test']);

        $this->actingAs($hr)->post(route('people.employee.store'), ['emp_sex' => 'unknown'])
            ->assertSessionHasErrors(['emp_first_name', 'emp_last_name', 'emp_sex', 'status']);

        $this->actingAs($hr)->post(route('people.employee.store'), $this->payload(['employee_number' => 'T-0001', 'work_email' => 'taken@example.test']))
            ->assertSessionHasErrors(['employee_number', 'work_email']);

        // Updating keeps its own number/email without tripping the unique rules.
        $this->actingAs($hr)->put(route('people.employee.update', $existing), $this->payload(['employee_number' => 'T-0001', 'work_email' => 'taken@example.test']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Juan', $existing->fresh()->emp_first_name);
    }

    #[Test]
    public function the_employee_number_cannot_be_blanked_on_update(): void
    {
        $existing = Employee::factory()->create(['employee_number' => 'T-0001']);

        $this->actingAs($this->userWithRole('hr_manager'))
            ->put(route('people.employee.update', $existing), $this->payload(['employee_number' => '']))
            ->assertSessionHasErrors('employee_number');

        $this->assertSame('T-0001', $existing->fresh()->employee_number);
    }
}
