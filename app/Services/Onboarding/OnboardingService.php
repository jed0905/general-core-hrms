<?php

namespace App\Services\Onboarding;

use App\Models\ApplicationConversion;
use App\Models\Employee;
use App\Models\Onboarding;
use App\Models\OnboardingEvent;
use App\Models\OnboardingNote;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateTask;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Onboarding cases: start from a template, complete, cancel, notes, extra tasks.
 *
 * Onboarding always belongs to an existing employee (Core HR); it never
 * creates employees, numbers or movements.
 *
 * Starting copies the template's active tasks into onboarding_tasks, resolving
 * each assignee (the employee, HR, the employee's supervisor today, or a named
 * employee) and due date into stored values, so later template or supervisor
 * changes never rewrite a case. The employee row is locked first, before any
 * plain read, and a unique index allows one active case per employee.
 */
class OnboardingService
{
    private const INACTIVE_EMPLOYEE = ['archived', 'terminated'];

    public function __construct(protected OnboardingEventRecorder $events) {}

    public function getPaginatedOnboardings(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->withProgress(Onboarding::query())
            ->with([
                'employee:id,employee_number,emp_first_name,emp_last_name,department_id,supervisor_id',
                'employee.department:id,name',
            ])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status), fn ($q) => $q->whereIn('status', Onboarding::ACTIVE))
            ->when($filters['template_id'] ?? null, fn ($q, $id) => $q->where('onboarding_template_id', $id))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $id)))
            ->when($filters['supervisor_id'] ?? null, fn ($q, $id) => $q->whereHas('employee', fn ($e) => $e->where('supervisor_id', $id)))
            ->when($filters['start_from'] ?? null, fn ($q, $d) => $q->whereDate('start_date', '>=', $d))
            ->when($filters['start_to'] ?? null, fn ($q, $d) => $q->whereDate('start_date', '<=', $d))
            ->when($filters['overdue'] ?? null, fn ($q) => $q->whereIn('status', Onboarding::ACTIVE)->whereHas('tasks', fn ($t) => $t->overdue()))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->whereHas('employee', fn ($e) => $e
                ->where('employee_number', 'like', "%{$search}%")
                ->orWhere('emp_first_name', 'like', "%{$search}%")
                ->orWhere('emp_last_name', 'like', "%{$search}%")))
            ->orderBy('start_date')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Progress counted from the tasks (never stored): all active tasks, and required ones.
     */
    public function withProgress(Builder $query): Builder
    {
        $counted = fn ($q) => $q->whereNotIn('status', [OnboardingTask::STATUS_CANCELLED]);
        $done = fn ($q) => $q->where('status', OnboardingTask::STATUS_COMPLETED);

        return $query->withCount([
            'tasks as tasks_total' => $counted,
            'tasks as tasks_done' => fn ($q) => $q->whereIn('status', [OnboardingTask::STATUS_COMPLETED, OnboardingTask::STATUS_SKIPPED]),
            'tasks as required_total' => fn ($q) => $counted($q)->where('is_required', true),
            'tasks as required_done' => fn ($q) => $done($q)->where('is_required', true)
                ->where(fn ($v) => $v->where('requires_verification', false)->orWhereNotNull('verified_at')),
            'tasks as overdue_count' => fn ($q) => $q->overdue(),
        ]);
    }

    /**
     * @return array{pending: int, in_progress: int, completed_this_month: int, overdue_tasks: int, due_this_week: int}
     */
    public function dashboard(): array
    {
        $active = fn ($q) => $q->whereIn('status', Onboarding::ACTIVE);

        return [
            'pending' => Onboarding::where('status', Onboarding::STATUS_PENDING)->count(),
            'in_progress' => Onboarding::where('status', Onboarding::STATUS_IN_PROGRESS)->count(),
            'completed_this_month' => Onboarding::where('status', Onboarding::STATUS_COMPLETED)->where('completed_at', '>=', now()->startOfMonth())->count(),
            'overdue_tasks' => OnboardingTask::overdue()->whereHas('onboarding', $active)->count(),
            'due_this_week' => OnboardingTask::whereIn('status', OnboardingTask::OPEN)
                ->whereBetween('due_date', [today()->toDateString(), today()->addDays(7)->toDateString()])
                ->whereHas('onboarding', $active)->count(),
        ];
    }

    /**
     * Why this employee can't start onboarding now (null when they can).
     */
    public function eligibilityProblem(Employee $employee, bool $confirmReonboarding = false): ?string
    {
        if (in_array($employee->status, self::INACTIVE_EMPLOYEE, true)) {
            return "This employee is {$employee->status}; onboarding is only for current employees.";
        }

        $cases = Onboarding::where('employee_id', $employee->id)->pluck('status');
        if ($cases->intersect(Onboarding::ACTIVE)->isNotEmpty()) {
            return 'This employee already has an active onboarding.';
        }
        if ($cases->contains(Onboarding::STATUS_COMPLETED) && ! $confirmReonboarding) {
            return 'This employee already completed onboarding. Confirm re-onboarding to start another.';
        }

        return null;
    }

    /**
     * @param  array{onboarding_template_id: int, start_date: string, target_completion_date?: ?string, notes?: ?string, application_conversion_id?: ?int, confirm_reonboarding?: bool}  $data
     */
    public function start(Employee $employee, array $data, User $actor): Onboarding
    {
        try {
            return DB::transaction(function () use ($employee, $data, $actor) {
                $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();

                if (! $actor->can('onboarding.create')) {
                    throw ValidationException::withMessages(['employee_id' => ['You are not allowed to start onboarding.']]);
                }
                if ($problem = $this->eligibilityProblem($employee, ! empty($data['confirm_reonboarding']))) {
                    throw ValidationException::withMessages(['employee_id' => [$problem]]);
                }

                $template = OnboardingTemplate::whereKey($data['onboarding_template_id'])->where('is_active', true)->first();
                if (! $template) {
                    throw ValidationException::withMessages(['onboarding_template_id' => ['Choose an active onboarding template.']]);
                }
                $definitions = $template->tasks()->where('is_active', true)->get();
                if ($definitions->isEmpty()) {
                    throw ValidationException::withMessages(['onboarding_template_id' => ['This template has no active tasks.']]);
                }

                $conversionId = $data['application_conversion_id'] ?? null;
                if ($conversionId && ! ApplicationConversion::whereKey($conversionId)->where('employee_id', $employee->id)->exists()) {
                    throw ValidationException::withMessages(['application_conversion_id' => ['That recruitment conversion is not for this employee.']]);
                }

                $onboarding = Onboarding::create([
                    'employee_id' => $employee->id,
                    'onboarding_template_id' => $template->id,
                    'template_name' => $template->name,
                    'application_conversion_id' => $conversionId,
                    'status' => Onboarding::STATUS_PENDING,
                    'start_date' => $data['start_date'],
                    'target_completion_date' => $data['target_completion_date'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor->id,
                ]);
                $this->events->record($onboarding, OnboardingEvent::CREATED, $actor, "Template: {$template->name}; start {$data['start_date']}.");

                foreach ($definitions as $definition) {
                    $this->createTask($onboarding, $employee, $definition->only([
                        'title', 'description', 'category', 'sort_order', 'assignee_type', 'assignee_employee_id', 'is_required',
                        'employee_visible', 'requires_verification', 'required_document_type_id', 'due_relative_to', 'due_offset_days',
                    ]) + ['onboarding_template_task_id' => $definition->id], $actor);
                }

                return $onboarding;
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request started this employee's onboarding first (database-enforced).
            throw ValidationException::withMessages(['employee_id' => ['This employee already has an active onboarding.']]);
        }
    }

    /**
     * An extra task for one onboarding (not from the template).
     */
    public function addTask(Onboarding $onboarding, array $data, User $actor): OnboardingTask
    {
        return DB::transaction(function () use ($onboarding, $data, $actor) {
            $onboarding = $this->lockActive($onboarding);
            $employee = Employee::findOrFail($onboarding->employee_id);

            return $this->createTask($onboarding, $employee, $data + [
                'sort_order' => (int) $onboarding->tasks()->max('sort_order') + 10,
                'due_relative_to' => null,
            ], $actor);
        });
    }

    /**
     * Only when every required task is completed (and verified where required).
     */
    public function complete(Onboarding $onboarding, User $actor, ?string $remarks = null): Onboarding
    {
        return DB::transaction(function () use ($onboarding, $actor, $remarks) {
            $onboarding = $this->lockActive($onboarding);

            if (! $actor->can('onboarding.complete')) {
                throw ValidationException::withMessages(['status' => ['You are not allowed to complete onboarding.']]);
            }

            if ($problem = $this->completionProblem($onboarding)) {
                throw ValidationException::withMessages(['status' => [$problem]]);
            }

            $onboarding->update(['status' => Onboarding::STATUS_COMPLETED, 'completed_at' => now(), 'completed_by' => $actor->id]);
            $this->events->record($onboarding, OnboardingEvent::COMPLETED, $actor, $remarks);

            return $onboarding;
        });
    }

    /**
     * Why the onboarding can't be completed yet (null when it can).
     */
    public function completionProblem(Onboarding $onboarding): ?string
    {
        $required = OnboardingTask::where('onboarding_id', $onboarding->id)->where('is_required', true)->get();

        $incomplete = $required->filter(fn ($t) => $t->status !== OnboardingTask::STATUS_COMPLETED)->count();
        if ($incomplete > 0) {
            return "Onboarding cannot be completed because {$incomplete} required task(s) remain incomplete.";
        }

        $unverified = $required->reject->isSatisfied()->count();
        if ($unverified > 0) {
            return "Onboarding cannot be completed because {$unverified} required task(s) still need HR verification.";
        }

        return null;
    }

    public function cancel(Onboarding $onboarding, string $reason, User $actor): Onboarding
    {
        return DB::transaction(function () use ($onboarding, $reason, $actor) {
            $onboarding = $this->lockActive($onboarding);

            if (! $actor->can('onboarding.cancel')) {
                throw ValidationException::withMessages(['status' => ['You are not allowed to cancel onboarding.']]);
            }

            $open = OnboardingTask::where('onboarding_id', $onboarding->id)->whereIn('status', OnboardingTask::OPEN)->lockForUpdate()->get();
            foreach ($open as $task) {
                $task->update(['status' => OnboardingTask::STATUS_CANCELLED, 'cancelled_at' => now()]);
            }

            $onboarding->update(['status' => Onboarding::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason]);
            $this->events->record($onboarding, OnboardingEvent::CANCELLED, $actor, $reason." ({$open->count()} open task(s) cancelled)");

            return $onboarding;
        });
    }

    public function addNote(Onboarding $onboarding, string $body, User $actor): OnboardingNote
    {
        $employee = $actor->employee_id ? Employee::find($actor->employee_id) : null;

        return $onboarding->notes()->create([
            'author_user_id' => $actor->id,
            'author_name' => $employee ? trim("{$employee->emp_first_name} {$employee->emp_last_name}") : $actor->username,
            'body' => $body,
        ]);
    }

    // ----------------------------------------------------------------- internals

    protected function lockActive(Onboarding $onboarding): Onboarding
    {
        $onboarding = Onboarding::whereKey($onboarding->id)->lockForUpdate()->firstOrFail();

        if (! $onboarding->isActive()) {
            throw ValidationException::withMessages(['status' => ["This onboarding is already {$onboarding->status}."]]);
        }

        return $onboarding;
    }

    /**
     * Snapshot one task: resolve the assignee and the due date into stored values.
     */
    protected function createTask(Onboarding $onboarding, Employee $employee, array $definition, User $actor): OnboardingTask
    {
        [$assigneeType, $assigneeId, $note] = $this->resolveAssignee($employee, $definition['assignee_type'], $definition['assignee_employee_id'] ?? null);

        $task = OnboardingTask::create([
            'onboarding_id' => $onboarding->id,
            'onboarding_template_task_id' => $definition['onboarding_template_task_id'] ?? null,
            'sort_order' => $definition['sort_order'] ?? 0,
            'title' => $definition['title'],
            'description' => $definition['description'] ?? null,
            'category' => $definition['category'],
            'assignee_type' => $assigneeType,
            'assignee_employee_id' => $assigneeId,
            'is_required' => (bool) ($definition['is_required'] ?? true),
            'employee_visible' => $assigneeType === OnboardingTemplateTask::ASSIGNEE_EMPLOYEE ? true : (bool) ($definition['employee_visible'] ?? true),
            'requires_verification' => (bool) ($definition['requires_verification'] ?? false),
            'required_document_type_id' => $definition['required_document_type_id'] ?? null,
            'due_date' => $definition['due_date'] ?? $this->dueDate($onboarding, $definition),
            'status' => OnboardingTask::STATUS_PENDING,
            'created_by' => $actor->id,
        ]);

        $this->events->record($onboarding, OnboardingEvent::TASK_CREATED, $actor, $task->title, $task);
        $this->events->record($onboarding, OnboardingEvent::TASK_ASSIGNED, $actor, $this->assigneeLabel($task).($note ? " ({$note})" : ''), $task);

        return $task;
    }

    /**
     * @return array{0: string, 1: ?int, 2: ?string} type, employee id (null for the HR pool), fallback note
     */
    protected function resolveAssignee(Employee $employee, string $type, ?int $specificId): array
    {
        return match ($type) {
            OnboardingTemplateTask::ASSIGNEE_EMPLOYEE => [$type, $employee->id, null],
            OnboardingTemplateTask::ASSIGNEE_SUPERVISOR => $employee->supervisor_id && $this->isCurrent($employee->supervisor_id)
                ? [$type, $employee->supervisor_id, null]
                : [OnboardingTemplateTask::ASSIGNEE_HR, null, 'no current supervisor; assigned to HR'],
            OnboardingTemplateTask::ASSIGNEE_SPECIFIC => $specificId && $this->isCurrent($specificId)
                ? [$type, $specificId, null]
                : [OnboardingTemplateTask::ASSIGNEE_HR, null, 'the designated employee is no longer current; assigned to HR'],
            default => [OnboardingTemplateTask::ASSIGNEE_HR, null, null],
        };
    }

    protected function isCurrent(int $employeeId): bool
    {
        return Employee::whereKey($employeeId)->whereNotIn('status', self::INACTIVE_EMPLOYEE)->exists();
    }

    protected function dueDate(Onboarding $onboarding, array $definition): string
    {
        $base = ($definition['due_relative_to'] ?? OnboardingTemplateTask::RELATIVE_START) === OnboardingTemplateTask::RELATIVE_CREATED
            ? today()
            : Carbon::parse($onboarding->start_date);

        return $base->copy()->addDays((int) ($definition['due_offset_days'] ?? 0))->toDateString();
    }

    protected function assigneeLabel(OnboardingTask $task): string
    {
        $name = $task->assignee_employee_id ? Employee::whereKey($task->assignee_employee_id)->first(['emp_first_name', 'emp_last_name']) : null;

        return 'Assigned to '.match ($task->assignee_type) {
            OnboardingTemplateTask::ASSIGNEE_EMPLOYEE => 'the employee',
            OnboardingTemplateTask::ASSIGNEE_HR => 'HR',
            OnboardingTemplateTask::ASSIGNEE_SUPERVISOR => 'supervisor '.trim("{$name?->emp_first_name} {$name?->emp_last_name}"),
            default => trim("{$name?->emp_first_name} {$name?->emp_last_name}"),
        };
    }
}
