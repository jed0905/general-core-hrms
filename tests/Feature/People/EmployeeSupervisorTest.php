<?php

namespace Tests\Feature\People;

use App\Models\Employee;
use App\Services\EmployeeService;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * Employee::supervisor() resolves employees.supervisor_id to the supervisor
 * (it previously returned one of the employee's subordinates).
 */
class EmployeeSupervisorTest extends LeaveTestCase
{
    private Employee $manager;

    private Employee $lead;

    private Employee $developer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Employee::factory()->create(['emp_first_name' => 'Daniel', 'emp_last_name' => 'Cruz']);
        $this->lead = Employee::factory()->create(['emp_first_name' => 'Angela', 'emp_last_name' => 'Bautista', 'supervisor_id' => $this->manager->id]);
        $this->developer = Employee::factory()->create(['emp_first_name' => 'Kevin', 'emp_last_name' => 'Lim', 'supervisor_id' => $this->lead->id]);
    }

    #[Test]
    public function supervisor_is_the_employee_referenced_by_supervisor_id(): void
    {
        $this->assertTrue($this->lead->supervisor->is($this->manager));
        $this->assertTrue($this->developer->supervisor->is($this->lead));
        $this->assertNull($this->manager->supervisor, 'top of the hierarchy has no supervisor');
    }

    #[Test]
    public function profile_page_shows_the_actual_supervisor(): void
    {
        $this->actingAs($this->userWithRole('hr_director'))
            ->get(route('people.employee.show', $this->lead))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('employee.supervisor.id', $this->manager->id)
                ->where('employee.supervisor.emp_first_name', 'Daniel')
                ->where('employee.supervisor.emp_last_name', 'Cruz'));
    }

    #[Test]
    public function employee_list_eager_loads_each_supervisor(): void
    {
        $page = app(EmployeeService::class)->getPaginatedEmployees(['search' => 'Bautista']);

        $this->assertSame('Cruz', $page->items()[0]->supervisor->emp_last_name);
    }

    #[Test]
    public function constrained_eager_load_selects_only_the_requested_columns(): void
    {
        $employees = Employee::with('supervisor:id,emp_first_name,emp_last_name')
            ->whereIn('id', [$this->manager->id, $this->lead->id, $this->developer->id])
            ->get()
            ->keyBy('id');

        $this->assertSame(['id', 'emp_first_name', 'emp_last_name'], array_keys($employees[$this->lead->id]->supervisor->getAttributes()));
        $this->assertSame('Daniel', $employees[$this->lead->id]->supervisor->emp_first_name);
        $this->assertSame('Angela', $employees[$this->developer->id]->supervisor->emp_first_name);
        $this->assertNull($employees[$this->manager->id]->supervisor);
    }

    #[Test]
    public function employee_list_page_carries_each_employees_supervisor(): void
    {
        $this->actingAs($this->userWithRole('hr_director'))
            ->get(route('people.employee.index', ['search' => 'Lim']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('employees.data.0.emp_last_name', 'Lim')
                ->where('employees.data.0.supervisor.id', $this->lead->id)
                ->where('employees.data.0.supervisor.emp_first_name', 'Angela')
                ->where('employees.data.0.supervisor.emp_last_name', 'Bautista'));
    }
}
