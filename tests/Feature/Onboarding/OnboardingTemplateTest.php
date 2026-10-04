<?php

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateTask;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;

class OnboardingTemplateTest extends OnboardingTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Remote Employee Onboarding', 'description' => null, 'employment_status_id' => null, 'is_active' => true, 'tasks' => $this->taskRows()], $overrides);
    }

    #[Test]
    public function hr_management_creates_and_edits_templates_with_tasks(): void
    {
        $this->actingAs($this->hrManager)->post(route('people.onboarding-templates.store'), $this->payload())->assertSessionHasNoErrors();
        $template = OnboardingTemplate::sole();
        $this->assertSame(6, $template->tasks()->count());
        $this->assertSame([10, 20, 30, 40, 50, 60], $template->tasks->pluck('sort_order')->all());

        $tasks = $template->tasks->map(fn ($t) => $t->only(['id', 'title', 'description', 'category', 'assignee_type', 'assignee_employee_id', 'due_relative_to', 'due_offset_days', 'is_required', 'employee_visible', 'requires_verification', 'required_document_type_id', 'is_active']))->all();
        $tasks[0]['title'] = 'Submit government ID and tax ID';
        unset($tasks[5]); // remove one
        $tasks[] = ['title' => 'Welcome lunch', 'description' => null, 'category' => 'first_week', 'assignee_type' => 'hr', 'assignee_employee_id' => null, 'due_relative_to' => 'start_date', 'due_offset_days' => 3, 'is_required' => false, 'employee_visible' => true, 'requires_verification' => false, 'required_document_type_id' => null, 'is_active' => true];

        $this->actingAs($this->hrManager)->put(route('people.onboarding-templates.update', $template), $this->payload(['is_active' => false, 'tasks' => array_values($tasks)]))->assertSessionHasNoErrors();
        $template->refresh();
        $this->assertFalse($template->is_active);
        $this->assertSame(['Submit government ID and tax ID', 'Prepare workstation', 'Meet supervisor', 'Review company policies', 'Set up IT accounts', 'Welcome lunch'], $template->tasks->pluck('title')->all());
        $this->assertSame($tasks[0]['id'], $template->tasks->first()->id, 'edited rows keep their id');
    }

    #[Test]
    public function templates_are_validated(): void
    {
        $this->template(['name' => 'Taken']);
        $bad = fn (array $over) => $this->actingAs($this->hrManager)->post(route('people.onboarding-templates.store'), $this->payload($over));

        $bad(['name' => 'Taken'])->assertSessionHasErrors('name');
        $bad(['tasks' => []])->assertSessionHasErrors('tasks');
        $bad(['tasks' => [array_merge($this->taskRows()[0], ['category' => 'whenever'])]])->assertSessionHasErrors('tasks.0.category');
        $bad(['tasks' => [array_merge($this->taskRows()[0], ['assignee_type' => 'robot'])]])->assertSessionHasErrors('tasks.0.assignee_type');
        $bad(['tasks' => [array_merge($this->taskRows()[0], ['assignee_type' => 'specific_employee', 'assignee_employee_id' => null])]])->assertSessionHasErrors('tasks.0.assignee_employee_id');
        $bad(['tasks' => [array_merge($this->taskRows()[0], ['due_offset_days' => 999])]])->assertSessionHasErrors('tasks.0.due_offset_days');
        $bad(['tasks' => [array_merge($this->taskRows()[0], ['required_document_type_id' => 99999])]])->assertSessionHasErrors('tasks.0.required_document_type_id');

        // A task id from another template can't be hijacked.
        $other = $this->template(['name' => 'Other'])->tasks->first();
        $mine = $this->template(['name' => 'Mine']);
        $this->actingAs($this->hrManager)->put(route('people.onboarding-templates.update', $mine), $this->payload(['name' => 'Mine', 'tasks' => [array_merge($this->taskRows()[0], ['id' => $other->id])]]))
            ->assertSessionHasErrors('tasks.0.id');
        $this->assertSame('Submit government ID', $other->fresh()->title);
    }

    #[Test]
    public function only_template_permissions_reach_templates(): void
    {
        $template = $this->template();

        $this->actingAs($this->hr)->get(route('people.onboarding-templates.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('app/People/OnboardingTemplates/Index', false)->where('can.create', false)->has('templates.data', 1));
        $this->actingAs($this->hr)->post(route('people.onboarding-templates.store'), $this->payload())->assertForbidden();
        $this->actingAs($this->hr)->put(route('people.onboarding-templates.update', $template), $this->payload())->assertForbidden();

        foreach ([$this->sam, $this->nina, $this->userWithRole('payroll')] as $user) {
            $this->actingAs($user)->get(route('people.onboarding-templates.index'))->assertForbidden();
            $this->actingAs($user)->post(route('people.onboarding-templates.store'), $this->payload())->assertForbidden();
        }
        $this->assertSame(1, OnboardingTemplate::count());
        $this->assertSame(6, OnboardingTemplateTask::count());
    }
}
