<?php

namespace App\Services\Onboarding;

use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateTask;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Onboarding templates and their task definitions. Saving a template replaces
 * its task list (rows keep their ids when edited); onboardings already created
 * keep their own task snapshots and are never touched.
 */
class OnboardingTemplateService
{
    private const TASK_FIELDS = [
        'title', 'description', 'category', 'sort_order', 'assignee_type', 'assignee_employee_id', 'due_relative_to',
        'due_offset_days', 'is_required', 'employee_visible', 'requires_verification', 'required_document_type_id', 'is_active',
    ];

    public function getPaginatedTemplates(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return OnboardingTemplate::query()
            ->withCount(['tasks', 'tasks as active_tasks_count' => fn ($q) => $q->where('is_active', true)])
            ->with('employmentStatus:id,name')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when(isset($filters['active']) && $filters['active'] !== '', fn ($q) => $q->where('is_active', (bool) $filters['active']))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createTemplate(array $data, User $actor): OnboardingTemplate
    {
        return DB::transaction(function () use ($data, $actor) {
            $template = OnboardingTemplate::create(Arr::only($data, ['name', 'description', 'employment_status_id', 'is_active']) + ['created_by' => $actor->id]);
            $this->syncTasks($template, $data['tasks'] ?? []);

            return $template;
        });
    }

    public function updateTemplate(OnboardingTemplate $template, array $data, User $actor): OnboardingTemplate
    {
        return DB::transaction(function () use ($template, $data, $actor) {
            $template = OnboardingTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();
            $template->update(Arr::only($data, ['name', 'description', 'employment_status_id', 'is_active']) + ['updated_by' => $actor->id]);
            $this->syncTasks($template, $data['tasks'] ?? []);

            return $template;
        });
    }

    /**
     * Rows with an id are updated (they must belong to this template); rows
     * without are added; missing rows are removed. Existing onboarding tasks
     * only keep a nullable reference to these rows.
     */
    protected function syncTasks(OnboardingTemplate $template, array $tasks): void
    {
        $keep = [];

        foreach (array_values($tasks) as $i => $row) {
            $attributes = Arr::only($row, self::TASK_FIELDS) + ['sort_order' => ($i + 1) * 10];
            if (($attributes['assignee_type'] ?? null) !== OnboardingTemplateTask::ASSIGNEE_SPECIFIC) {
                $attributes['assignee_employee_id'] = null;
            }

            if (! empty($row['id'])) {
                $task = OnboardingTemplateTask::where('onboarding_template_id', $template->id)->whereKey($row['id'])->first();
                if (! $task) {
                    throw ValidationException::withMessages(["tasks.{$i}.id" => ['This task belongs to another template.']]);
                }
                $task->update($attributes);
            } else {
                $task = $template->tasks()->create($attributes);
            }
            $keep[] = $task->id;
        }

        OnboardingTemplateTask::where('onboarding_template_id', $template->id)->whereNotIn('id', $keep)->delete();
    }
}
