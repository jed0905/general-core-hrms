<?php

namespace Tests\Feature\Dashboard;

use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * HR dashboard → Employees by Location: { id, location, employee_count } per
 * location, counting active employees, plus an "Unassigned" bucket.
 */
class EmployeesByLocationTest extends LeaveTestCase
{
    private Location $main;

    private Location $north;

    private Location $empty;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = $this->userWithRole('hr_director'); // also creates the HR user's own employee record

        $this->main = Location::create(['address' => 'Main Corporate Office', 'city' => 'Pasig City']);
        $this->north = Location::create(['address' => 'North Branch', 'city' => 'Quezon City']);
        $this->empty = Location::create(['address' => 'Satellite Office', 'city' => 'Pasig City']);

        Employee::factory()->count(3)->create(['location_id' => $this->main->id, 'status' => 'active']);
        Employee::factory()->create(['location_id' => $this->north->id, 'status' => 'active']);
        Employee::factory()->create(['location_id' => $this->north->id, 'status' => 'terminated']);
        Employee::factory()->create(['location_id' => $this->empty->id, 'status' => 'terminated']);
    }

    private function locations(): array
    {
        return $this->actingAs($this->hr)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('app/Dashboard', false))
            ->viewData('page')['props']['employeesPerLocation'];
    }

    /**
     * Active employees per location_id straight from the database (null = unassigned).
     */
    private function activeCountsByLocationId(): array
    {
        return Employee::where('status', 'active')->get()->countBy(fn ($e) => $e->location_id ?? 'unassigned')->all();
    }

    #[Test]
    public function each_location_is_labelled_with_its_address_and_counts_active_employees(): void
    {
        $rows = collect($this->locations())->keyBy('id');
        $expected = $this->activeCountsByLocationId();

        $this->assertSame(['id' => $this->main->id, 'location' => 'Main Corporate Office', 'employee_count' => $expected[$this->main->id]], $rows[$this->main->id]);
        $this->assertSame(['id' => $this->north->id, 'location' => 'North Branch', 'employee_count' => 1], $rows[$this->north->id]);
        $this->assertSame('Main Corporate Office', $rows->first()['location'], 'largest location first');
    }

    #[Test]
    public function locations_without_active_employees_stay_on_the_chart_with_zero(): void
    {
        $rows = collect($this->locations())->keyBy('id');

        $this->assertSame(['id' => $this->empty->id, 'location' => 'Satellite Office', 'employee_count' => 0], $rows[$this->empty->id]);
        $this->assertCount(Location::count(), $rows->whereNotNull('id'));
    }

    #[Test]
    public function active_employees_without_a_location_are_unassigned_never_undefined(): void
    {
        Employee::factory()->count(2)->create(['location_id' => null, 'status' => 'active']);
        $expectedUnassigned = $this->activeCountsByLocationId()['unassigned'];

        $rows = collect($this->locations());

        $this->assertSame(['id' => null, 'location' => 'Unassigned', 'employee_count' => $expectedUnassigned], $rows->last());
        $rows->each(fn ($row) => $this->assertTrue(is_string($row['location']) && $row['location'] !== '' && $row['location'] !== 'undefined'));
        $this->assertSame(Employee::where('status', 'active')->count(), $rows->sum('employee_count'), 'chart total matches active employees');
    }

    #[Test]
    public function no_unassigned_bucket_when_every_active_employee_has_a_location(): void
    {
        Employee::whereNull('location_id')->update(['location_id' => $this->main->id]);

        $this->assertNotContains('Unassigned', collect($this->locations())->pluck('location'));
    }

    #[Test]
    public function a_location_without_an_address_falls_back_to_its_city(): void
    {
        $cityOnly = Location::create(['address' => null, 'city' => 'Cebu City']);

        $this->assertSame('Cebu City', collect($this->locations())->firstWhere('id', $cityOnly->id)['location']);
    }

    #[Test]
    public function the_widget_is_only_sent_to_hr_dashboard_users(): void
    {
        $this->actingAs($this->userWithRole('employee'))
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('employeesPerLocation'));
    }
}
