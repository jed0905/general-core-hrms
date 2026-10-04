<?php

namespace Tests\Feature\People;

use App\Models\Employee;
use App\Models\NumberSequence;
use App\Services\EmployeeService;
use App\Services\NumberSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * Configurable employee numbering (number_sequences / NumberSequenceService).
 * Real parallel-process concurrency is covered by tests/Concurrency against MySQL.
 */
class EmployeeNumberGeneratorTest extends LeaveTestCase
{
    private function sequence(): NumberSequence
    {
        return NumberSequence::where('key', NumberSequence::EMPLOYEE_NUMBER)->firstOrFail();
    }

    private function create(array $overrides = []): Employee
    {
        return app(EmployeeService::class)->createEmployee(array_merge([
            'employee_number' => null,
            'emp_first_name' => 'New',
            'emp_last_name' => 'Hire',
            'emp_sex' => 'female',
            'status' => 'active',
        ], $overrides));
    }

    #[Test]
    public function the_default_sequence_issues_consecutive_padded_numbers(): void
    {
        $this->assertSame(['EMP-', 5, 1, true], [$this->sequence()->prefix, $this->sequence()->padding, $this->sequence()->next_number, $this->sequence()->auto_generate]);

        $this->assertSame(['EMP-00001', 'EMP-00002', 'EMP-00003'], [
            $this->create()->employee_number,
            $this->create()->employee_number,
            $this->create()->employee_number,
        ]);
        $this->assertSame(4, $this->sequence()->next_number);
    }

    #[Test]
    public function the_format_is_configurable_per_organization(): void
    {
        Carbon::setTestNow('2026-10-03');
        $this->sequence()->update(['prefix' => 'HR-{YYYY}-', 'suffix' => '-PH', 'padding' => 3, 'next_number' => 7]);

        $this->assertSame('HR-2026-007-PH', app(NumberSequenceService::class)->preview(NumberSequence::EMPLOYEE_NUMBER));
        $this->assertSame('HR-2026-007-PH', $this->create()->employee_number);
        $this->assertSame('HR-2026-008-PH', $this->create()->employee_number);
    }

    #[Test]
    public function the_hr_form_generates_a_number_when_left_blank_and_keeps_a_typed_one(): void
    {
        $hr = $this->userWithRole('hr_staff');
        $payload = ['emp_first_name' => 'Ana', 'emp_last_name' => 'Reyes', 'emp_sex' => 'female', 'status' => 'active'];

        $this->actingAs($hr)->post(route('people.employee.store'), $payload + ['employee_number' => ''])->assertSessionHasNoErrors();
        $this->actingAs($hr)->post(route('people.employee.store'), $payload + ['employee_number' => 'MANUAL-9'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', ['employee_number' => 'EMP-00001']);
        $this->assertDatabaseHas('employees', ['employee_number' => 'MANUAL-9']);
        $this->assertSame(2, $this->sequence()->next_number, 'a typed number does not consume the sequence');
    }

    #[Test]
    public function numbers_already_used_manually_are_skipped(): void
    {
        Employee::factory()->create(['employee_number' => 'EMP-00001']);
        Employee::factory()->create(['employee_number' => 'EMP-00002']);

        $this->assertSame('EMP-00003', $this->create()->employee_number);
        $this->assertSame(4, $this->sequence()->next_number);
    }

    #[Test]
    public function a_rolled_back_creation_returns_its_number(): void
    {
        try {
            DB::transaction(function () {
                $this->create();
                throw new RuntimeException('later step failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, Employee::count());
        $this->assertSame('EMP-00001', $this->create()->employee_number, 'no gap after a rollback');
    }

    #[Test]
    public function when_automatic_numbering_is_off_a_number_must_be_entered(): void
    {
        $this->sequence()->update(['auto_generate' => false]);

        $this->actingAs($this->userWithRole('hr_staff'))
            ->post(route('people.employee.store'), ['emp_first_name' => 'Ana', 'emp_last_name' => 'Reyes', 'emp_sex' => 'female', 'status' => 'active'])
            ->assertSessionHasErrors('employee_number');

        $this->expectException(ValidationException::class);
        $this->create();
    }

    #[Test]
    public function the_create_page_shows_the_next_number_without_consuming_it(): void
    {
        $this->actingAs($this->userWithRole('hr_staff'))
            ->get(route('people.employee.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('employeeNumber.auto', true)->where('employeeNumber.preview', 'EMP-00001'));

        $this->assertSame(1, $this->sequence()->next_number);
    }

    #[Test]
    public function an_unconfigured_sequence_fails_loudly(): void
    {
        $this->expectException(RuntimeException::class);
        app(NumberSequenceService::class)->next('requisition_number');
    }
}
