<?php

namespace Tests\Feature\Onboarding;

use App\Models\Employee;
use App\Models\EmployeeDocumentType;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\User;
use App\Services\Onboarding\OnboardingService;
use App\Services\Onboarding\OnboardingTemplateService;
use Illuminate\Support\Carbon;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * Fixture: Nina (new hire, employee role) reports to Sam (supervisor). Ivan
 * (employee) is the designated IT contact. Ollie is an unrelated employee.
 * HR staff run onboarding; the HR manager may also cancel and edit templates.
 */
abstract class OnboardingTestCase extends LeaveTestCase
{
    protected User $hr;

    protected User $hrManager;

    protected User $sam;

    protected User $nina;

    protected User $ivan;

    protected User $ollie;

    protected int $idType;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 09:00:00');

        $this->hr = $this->userWithRole('hr_staff', ['emp_first_name' => 'Hana', 'emp_last_name' => 'HR']);
        $this->hrManager = $this->userWithRole('hr_manager', ['emp_first_name' => 'Mona', 'emp_last_name' => 'Manager']);
        $this->sam = $this->userWithRole('supervisor', ['emp_first_name' => 'Sam', 'emp_last_name' => 'Supervisor']);
        $this->nina = $this->userWithRole('employee', ['emp_first_name' => 'Nina', 'emp_last_name' => 'New', 'supervisor_id' => $this->sam->employee_id]);
        $this->ivan = $this->userWithRole('employee', ['emp_first_name' => 'Ivan', 'emp_last_name' => 'IT']);
        $this->ollie = $this->userWithRole('employee', ['emp_first_name' => 'Ollie', 'emp_last_name' => 'Other', 'supervisor_id' => $this->sam->employee_id]);
        $this->idType = (int) (EmployeeDocumentType::where('code', 'identification')->value('id')
            ?? EmployeeDocumentType::create(['code' => 'identification', 'name' => 'Identification', 'is_active' => true])->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function taskRows(): array
    {
        $row = fn (array $over) => array_merge([
            'description' => null, 'category' => 'first_day', 'assignee_type' => 'hr', 'assignee_employee_id' => null,
            'due_relative_to' => 'start_date', 'due_offset_days' => 0, 'is_required' => true, 'employee_visible' => true,
            'requires_verification' => false, 'required_document_type_id' => null, 'is_active' => true,
        ], $over);

        return [
            $row(['title' => 'Submit government ID', 'category' => 'before_start', 'assignee_type' => 'employee', 'due_offset_days' => -3, 'required_document_type_id' => $this->idType, 'requires_verification' => true]),
            $row(['title' => 'Prepare workstation', 'employee_visible' => false]),
            $row(['title' => 'Meet supervisor', 'assignee_type' => 'supervisor']),
            $row(['title' => 'Review company policies', 'category' => 'first_week', 'assignee_type' => 'employee', 'due_offset_days' => 7, 'is_required' => false]),
            $row(['title' => 'Set up IT accounts', 'assignee_type' => 'specific_employee', 'assignee_employee_id' => $this->ivan->employee_id, 'due_offset_days' => 1]),
            $row(['title' => 'Retired task', 'is_active' => false]),
        ];
    }

    protected function template(array $overrides = []): OnboardingTemplate
    {
        return app(OnboardingTemplateService::class)->createTemplate(array_merge([
            'name' => 'Standard New Employee Onboarding',
            'description' => 'Default checklist',
            'employment_status_id' => null,
            'is_active' => true,
            'tasks' => $this->taskRows(),
        ], $overrides), $this->hrManager);
    }

    protected function startFor(User|Employee $who, ?OnboardingTemplate $template = null, array $data = []): Onboarding
    {
        $employee = $who instanceof User ? Employee::findOrFail($who->employee_id) : $who;

        return app(OnboardingService::class)->start($employee, array_merge([
            'onboarding_template_id' => ($template ?? $this->template())->id,
            'start_date' => '2026-10-15',
        ], $data), $this->hr);
    }

    protected function task(Onboarding $onboarding, string $title): OnboardingTask
    {
        return $onboarding->tasks()->where('title', $title)->firstOrFail();
    }
}
