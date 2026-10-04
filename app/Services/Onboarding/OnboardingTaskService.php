<?php

namespace App\Services\Onboarding;

use App\Models\EmployeeDocument;
use App\Models\Onboarding;
use App\Models\OnboardingEvent;
use App\Models\OnboardingTask;
use App\Models\User;
use App\Services\EmployeeDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Onboarding task actions: start, complete, verify, skip.
 *
 * Every action locks the onboarding and then the task, re-checks the state and
 * the actor (OnboardingTaskPolicy) against the locked rows, writes the change
 * and an event in one transaction. A task's first activity moves a pending
 * onboarding to in progress.
 *
 * A task that requires a document type is completed with an employee document
 * of that type: uploaded through EmployeeDocumentService (private disk, random
 * names) or, by HR, chosen from the employee's existing documents.
 */
class OnboardingTaskService
{
    public function __construct(
        protected OnboardingEventRecorder $events,
        protected EmployeeDocumentService $documents
    ) {}

    public function start(OnboardingTask $task, User $actor): OnboardingTask
    {
        return DB::transaction(function () use ($task, $actor) {
            [$onboarding, $task] = $this->lock($task);
            $this->authorize($actor, 'act', $task);

            if ($task->status !== OnboardingTask::STATUS_PENDING) {
                throw ValidationException::withMessages(['status' => ["This task is already {$this->label($task->status)}."]]);
            }

            $task->update(['status' => OnboardingTask::STATUS_IN_PROGRESS, 'started_at' => now(), 'started_by' => $actor->id]);
            $this->events->record($onboarding, OnboardingEvent::TASK_STARTED, $actor, $task->title, $task);
            $this->markStarted($onboarding, $actor);

            return $task;
        });
    }

    /**
     * @param  array{remarks?: ?string, employee_document_id?: ?int, file?: ?UploadedFile}  $data
     */
    public function complete(OnboardingTask $task, array $data, User $actor): OnboardingTask
    {
        $stored = null;

        try {
            return DB::transaction(function () use ($task, $data, $actor, &$stored) {
                [$onboarding, $task] = $this->lock($task);
                $this->authorize($actor, 'act', $task);

                if (! $task->isOpen()) {
                    throw ValidationException::withMessages(['status' => ["This task is already {$this->label($task->status)}."]]);
                }

                $documentId = null;
                if ($task->required_document_type_id) {
                    $document = $this->documentFor($task, $onboarding, $data, $actor);
                    $stored = $document->wasRecentlyCreated ? $document->file_path : null;
                    $documentId = $document->id;
                }

                $task->update([
                    'status' => OnboardingTask::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'completed_by' => $actor->id,
                    'completion_remarks' => $data['remarks'] ?? null,
                    'employee_document_id' => $documentId,
                ]);
                $this->events->record($onboarding, OnboardingEvent::TASK_COMPLETED, $actor, $task->title.($data['remarks'] ?? null ? " – {$data['remarks']}" : ''), $task);
                $this->markStarted($onboarding, $actor);

                return $task;
            });
        } catch (Throwable $e) {
            if ($stored) {
                Storage::disk(EmployeeDocument::DISK)->delete($stored);
            }
            throw $e;
        }
    }

    /**
     * HR confirms a completed task that needs verification. The completer stays on record.
     */
    public function verify(OnboardingTask $task, User $actor): OnboardingTask
    {
        return DB::transaction(function () use ($task, $actor) {
            [$onboarding, $task] = $this->lock($task);
            $this->authorize($actor, 'verify', $task);

            if ($task->status !== OnboardingTask::STATUS_COMPLETED || ! $task->requires_verification || $task->verified_at) {
                throw ValidationException::withMessages(['status' => ['Only a completed task awaiting verification can be verified.']]);
            }
            if ($task->completed_by === $actor->id) {
                throw ValidationException::withMessages(['status' => ['Someone other than the person who completed the task must verify it.']]);
            }

            $task->update(['verified_at' => now(), 'verified_by' => $actor->id]);
            $this->events->record($onboarding, OnboardingEvent::TASK_VERIFIED, $actor, $task->title, $task);

            return $task;
        });
    }

    /**
     * Optional tasks only: a required task must be completed (the database refuses it too).
     */
    public function skip(OnboardingTask $task, string $reason, User $actor): OnboardingTask
    {
        return DB::transaction(function () use ($task, $reason, $actor) {
            [$onboarding, $task] = $this->lock($task);
            $this->authorize($actor, 'skip', $task);

            if ($task->is_required) {
                throw ValidationException::withMessages(['status' => ['Required tasks cannot be skipped.']]);
            }
            if (! $task->isOpen()) {
                throw ValidationException::withMessages(['status' => ["This task is already {$this->label($task->status)}."]]);
            }

            $task->update(['status' => OnboardingTask::STATUS_SKIPPED, 'skipped_at' => now(), 'skipped_by' => $actor->id, 'skip_reason' => $reason]);
            $this->events->record($onboarding, OnboardingEvent::TASK_SKIPPED, $actor, "{$task->title} – {$reason}", $task);

            return $task;
        });
    }

    // ----------------------------------------------------------------- internals

    /**
     * @return array{0: Onboarding, 1: OnboardingTask}
     */
    protected function lock(OnboardingTask $task): array
    {
        $onboarding = Onboarding::whereKey($task->onboarding_id)->lockForUpdate()->firstOrFail();
        $task = OnboardingTask::whereKey($task->id)->lockForUpdate()->firstOrFail();

        if (! $onboarding->isActive()) {
            throw ValidationException::withMessages(['status' => ["This onboarding is {$onboarding->status}; its tasks can no longer change."]]);
        }

        return [$onboarding, $task];
    }

    protected function authorize(User $actor, string $ability, OnboardingTask $task): void
    {
        if (! Gate::forUser($actor)->allows($ability, $task)) {
            throw ValidationException::withMessages(['status' => ['You are not allowed to do this for this task.']]);
        }
    }

    protected function markStarted(Onboarding $onboarding, User $actor): void
    {
        if ($onboarding->status === Onboarding::STATUS_PENDING) {
            $onboarding->update(['status' => Onboarding::STATUS_IN_PROGRESS]);
            $this->events->record($onboarding, OnboardingEvent::STARTED, $actor);
        }
    }

    protected function documentFor(OnboardingTask $task, Onboarding $onboarding, array $data, User $actor): EmployeeDocument
    {
        if (($data['file'] ?? null) instanceof UploadedFile) {
            return $this->documents->upload($onboarding->employee, $data['file'], [
                'employee_document_type_id' => $task->required_document_type_id,
                'description' => "Onboarding: {$task->title}",
            ], $actor);
        }

        if (! empty($data['employee_document_id'])) {
            // Choosing an existing file means seeing the employee's documents: HR only.
            if (! $actor->can('employee.documents.view')) {
                throw ValidationException::withMessages(['file' => ['Upload the document to complete this task.']]);
            }
            $document = EmployeeDocument::whereKey($data['employee_document_id'])
                ->where('employee_id', $onboarding->employee_id)
                ->where('employee_document_type_id', $task->required_document_type_id)
                ->first();
            if ($document) {
                return $document;
            }
        }

        throw ValidationException::withMessages(['file' => ['This task needs a document of the required type.']]);
    }

    protected function label(string $status): string
    {
        return str_replace('_', ' ', $status);
    }
}
