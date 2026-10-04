<?php

namespace Tests\Feature\People;

use App\Models\Employee;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * Regression: the create form used start_date/end_date/description and the edit
 * form description, while work_experiences stores from/to/notes, so dates and
 * descriptions were silently dropped. Canonical names are now from/to/notes.
 */
class EmployeeWorkExperienceTest extends LeaveTestCase
{
    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hr = $this->userWithRole('hr_staff');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'employee_number' => 'T-0001',
            'emp_first_name' => 'Maria',
            'emp_last_name' => 'Santos',
            'emp_sex' => 'female',
            'status' => 'active',
        ], $overrides);
    }

    private function experience(array $overrides = []): array
    {
        return array_merge([
            'company' => 'Globex Corp',
            'job_title' => 'Analyst',
            'from' => '2019-06-01',
            'to' => '2022-12-31',
            'notes' => 'Reporting and analytics.',
        ], $overrides);
    }

    #[Test]
    public function creating_an_employee_persists_work_experience_dates_and_description(): void
    {
        $this->actingAs($this->hr)
            ->post(route('people.employee.store'), $this->payload(['work_experience' => [$this->experience()]]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $row = Employee::where('employee_number', 'T-0001')->firstOrFail()->workExperience()->sole();

        $this->assertSame(['Globex Corp', 'Analyst', '2019-06-01', '2022-12-31', 'Reporting and analytics.'],
            [$row->company, $row->job_title, substr($row->from, 0, 10), substr($row->to, 0, 10), $row->notes]);
    }

    #[Test]
    public function updating_an_employee_replaces_work_experience_with_the_submitted_rows(): void
    {
        $this->actingAs($this->hr)->post(route('people.employee.store'), $this->payload(['work_experience' => [$this->experience()]]));
        $employee = Employee::where('employee_number', 'T-0001')->firstOrFail();

        $this->actingAs($this->hr)
            ->put(route('people.employee.update', $employee), $this->payload(['work_experience' => [
                $this->experience(['company' => 'Initech', 'notes' => 'Team lead.', 'from' => '2023-01-02', 'to' => '2025-03-31']),
            ]]))
            ->assertSessionHasNoErrors();

        $row = $employee->workExperience()->sole();
        $this->assertSame(['Initech', '2023-01-02', '2025-03-31', 'Team lead.'], [$row->company, substr($row->from, 0, 10), substr($row->to, 0, 10), $row->notes]);
    }

    #[Test]
    public function blank_rows_from_the_form_are_ignored(): void
    {
        $this->actingAs($this->hr)
            ->post(route('people.employee.store'), $this->payload(['work_experience' => [
                ['company' => '', 'job_title' => '', 'from' => null, 'to' => null, 'notes' => ''],
            ]]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Employee::where('employee_number', 'T-0001')->firstOrFail()->workExperience()->count());
    }

    #[Test]
    public function the_old_field_names_are_no_longer_accepted_silently(): void
    {
        $this->actingAs($this->hr)
            ->post(route('people.employee.store'), $this->payload(['work_experience' => [
                ['company' => 'Globex Corp', 'job_title' => 'Analyst', 'start_date' => '2019-06-01', 'end_date' => '2022-12-31', 'description' => 'x'],
            ]]))
            ->assertSessionHasErrors(['work_experience.0.from', 'work_experience.0.to']);

        $this->assertDatabaseMissing('employees', ['employee_number' => 'T-0001']);
    }

    #[Test]
    public function an_incomplete_or_reversed_row_is_rejected(): void
    {
        $this->actingAs($this->hr)
            ->post(route('people.employee.store'), $this->payload(['work_experience' => [['notes' => 'Only a description']]]))
            ->assertSessionHasErrors(['work_experience.0.company', 'work_experience.0.job_title']);

        $this->actingAs($this->hr)
            ->post(route('people.employee.store'), $this->payload(['work_experience' => [$this->experience(['from' => '2023-01-01', 'to' => '2020-01-01'])]]))
            ->assertSessionHasErrors('work_experience.0.to');
    }

    #[Test]
    public function the_description_respects_the_column_length(): void
    {
        $this->actingAs($this->hr)
            ->post(route('people.employee.store'), $this->payload(['work_experience' => [$this->experience(['notes' => str_repeat('a', 256)])]]))
            ->assertSessionHasErrors('work_experience.0.notes');
    }
}
