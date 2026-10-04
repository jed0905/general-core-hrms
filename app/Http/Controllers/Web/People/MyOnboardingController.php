<?php

namespace App\Http\Controllers\Web\People;

use App\Http\Controllers\Controller;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplateTask;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service: the logged-in user's own onboarding (employee-visible tasks
 * only, no HR notes or audit), plus onboarding tasks assigned to them for
 * other employees (as supervisor or designated employee). The employee is
 * always users.employee_id, never a request parameter.
 */
class MyOnboardingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employeeId = $user->employee_id;

        $onboarding = $employeeId
            ? Onboarding::where('employee_id', $employeeId)
                ->orderByRaw("case when status in ('pending', 'in_progress') then 0 else 1 end")->latest('id')->first()
            : null;

        // assignee_employee_id / employee_visible are needed by OnboardingTaskPolicy for can_act.
        $taskFields = ['id', 'onboarding_id', 'title', 'description', 'category', 'assignee_type', 'assignee_employee_id', 'employee_visible',
            'is_required', 'requires_verification', 'required_document_type_id', 'due_date', 'status', 'completed_at', 'verified_at'];

        $myTasks = $onboarding
            ? $onboarding->tasks()->where('employee_visible', true)->with('requiredDocumentType:id,name')->get($taskFields)
                ->each(fn ($t) => $t->setAttribute('can_act', $user->can('act', $t)))
            : collect();

        $assigned = $employeeId
            ? OnboardingTask::where('assignee_employee_id', $employeeId)
                ->whereIn('assignee_type', [OnboardingTemplateTask::ASSIGNEE_SUPERVISOR, OnboardingTemplateTask::ASSIGNEE_SPECIFIC])
                ->whereHas('onboarding', fn ($q) => $q->whereIn('status', Onboarding::ACTIVE)->where('employee_id', '!=', $employeeId))
                ->with(['onboarding:id,employee_id,start_date', 'onboarding.employee:id,emp_first_name,emp_last_name,employee_number', 'requiredDocumentType:id,name'])
                ->orderBy('due_date')
                ->get($taskFields)
                ->each(fn ($t) => $t->setAttribute('can_act', $user->can('act', $t)))
            : collect();

        return Inertia::render('app/People/MyOnboarding/Index', [
            'hasEmployeeRecord' => $employeeId !== null,
            'onboarding' => $onboarding ? Arr::only($onboarding->toArray(), ['id', 'status', 'template_name', 'start_date', 'target_completion_date', 'completed_at']) : null,
            'tasks' => $myTasks->values(),
            'assignedTasks' => $assigned->values(),
        ]);
    }
}
