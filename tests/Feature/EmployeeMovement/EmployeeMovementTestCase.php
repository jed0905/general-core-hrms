<?php

namespace Tests\Feature\EmployeeMovement;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeMovementType;
use App\Models\EmploymentStatus;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\EmployeeMovementTypeSeeder;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * Reuses the Leave test base (in-memory SQLite guard, seeded roles, user helpers).
 */
abstract class EmployeeMovementTestCase extends LeaveTestCase
{
    protected Department $it;

    protected Department $hr;

    protected JobTitle $junior;

    protected JobTitle $senior;

    protected EmploymentStatus $regular;

    protected EmploymentStatus $resigned;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmployeeMovementTypeSeeder::class);

        $this->it = Department::create(['name' => 'Information Technology', 'shortcut' => 'IT']);
        $this->hr = Department::create(['name' => 'Human Resources', 'shortcut' => 'HR']);
        $this->junior = JobTitle::create(['job_title' => 'Junior Developer']);
        $this->senior = JobTitle::create(['job_title' => 'Senior Developer']);
        $this->regular = EmploymentStatus::create(['name' => 'Regular']);
        $this->resigned = EmploymentStatus::create(['name' => 'Resigned']);
    }

    protected function type(string $code): EmployeeMovementType
    {
        return EmployeeMovementType::where('code', $code)->firstOrFail();
    }

    protected function staffMember(array $attributes = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'department_id' => $this->it->id,
            'job_title_id' => $this->junior->id,
            'employment_status_id' => $this->regular->id,
        ], $attributes));
    }

    protected function payload(Employee $employee, string $type, array $changes = [], array $extra = []): array
    {
        $data = [
            'employee_id' => $employee->id,
            'movement_type_id' => $this->type($type)->id,
            'effective_date' => now()->toDateString(),
            'reason' => 'Test movement',
            'changed_fields' => array_keys($changes),
        ];

        foreach ($changes as $field => $value) {
            $data["to_{$field}"] = $value;
        }

        return array_merge($data, $extra);
    }

    protected function record(User $user, array $payload)
    {
        return $this->actingAs($user)->post(route('people.employee-movements.store'), $payload);
    }
}
