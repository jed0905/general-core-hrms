<?php

namespace App\Services\Recruitment;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\Employee;
use App\Models\NumberSequence;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\NumberSequenceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating applications, linking submitted documents, internal notes and the
 * queries behind lists and the vacancy pipeline. Stage and status changes are
 * delegated to ApplicationPipelineService.
 */
class ApplicationService
{
    public function __construct(
        protected NumberSequenceService $numberSequences,
        protected VacancyStageService $stages,
        protected ApplicationPipelineService $pipeline
    ) {}

    /**
     * Everything with recruitment.application.view; otherwise only applications to
     * vacancies the user is hiring manager for.
     */
    public function visibleTo(User $user): Builder
    {
        return Application::query()->when(! $user->can('recruitment.application.view'), fn (Builder $q) => $user->employee_id
            ? $q->whereHas('vacancy', fn ($v) => $v->where('hiring_manager_id', $user->employee_id))
            : $q->whereRaw('1 = 0'));
    }

    public function getPaginatedApplications(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->visibleTo($user)
            ->with(['applicant:id,applicant_number,first_name,last_name', 'vacancy:id,vacancy_number,title', 'currentStage:id,name,stage_type'])
            ->when($filters['vacancy_id'] ?? null, fn ($q, $id) => $q->where('vacancy_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['stage_type'] ?? null, fn ($q, $type) => $q->whereHas('currentStage', fn ($s) => $s->where('stage_type', $type)))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('application_number', 'like', "%{$search}%")
                ->orWhereHas('applicant', fn ($a) => $a->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('applicant_number', 'like', "%{$search}%"))))
            ->latest('last_activity_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * $actor is the HR user; null for a careers-portal submission, which also
     * requires (under the same vacancy lock) that the vacancy is still on the
     * public careers site: published, external and before its closing date.
     *
     * @param  array{recruitment_source_id?: ?int, document_ids?: array<int>, remarks?: ?string}  $data
     */
    public function createApplication(Applicant $applicant, Vacancy $vacancy, array $data, ?User $actor, bool $public = false): Application
    {
        try {
            return DB::transaction(function () use ($applicant, $vacancy, $data, $actor, $public) {
                // Lock the vacancy so it can't close while the application is being added.
                $vacancy = Vacancy::whereKey($vacancy->id)->lockForUpdate()->firstOrFail();

                if ($public && ! $vacancy->isPubliclyOpen()) {
                    throw ValidationException::withMessages(['vacancy_id' => ['This position is no longer accepting applications.']]);
                }

                if ($vacancy->status !== Vacancy::STATUS_OPEN) {
                    throw ValidationException::withMessages(['vacancy_id' => ["This vacancy is not accepting applications (status: {$vacancy->status})."]]);
                }

                if ($applicant->fresh()->status !== Applicant::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['applicant_id' => ['Archived applicants cannot apply.']]);
                }

                if (Application::where('applicant_id', $applicant->id)->where('vacancy_id', $vacancy->id)->exists()) {
                    throw $this->alreadyApplied();
                }

                $firstStage = $this->stages->snapshot($vacancy)->first();

                $number = $this->numberSequences->next(
                    NumberSequence::APPLICATION_NUMBER,
                    fn (string $candidate) => Application::where('application_number', $candidate)->exists()
                );

                $application = Application::create([
                    'application_number' => $number,
                    'applicant_id' => $applicant->id,
                    'vacancy_id' => $vacancy->id,
                    'current_vacancy_stage_id' => $firstStage->id,
                    'status' => Application::STATUS_ACTIVE,
                    'recruitment_source_id' => $data['recruitment_source_id'] ?? $applicant->recruitment_source_id,
                    'applied_at' => now(),
                    'last_activity_at' => now(),
                    'created_by' => $actor?->id,
                ]);

                $this->pipeline->recordApplied($application, $actor, $data['remarks'] ?? null);
                $this->attachDocuments($application, $data['document_ids'] ?? [], $actor);

                return $application;
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request created the same application first (database-enforced).
            if (str_contains($e->getMessage(), 'applications_applicant_vacancy_unique') || str_contains($e->getMessage(), 'applications.applicant_id, applications.vacancy_id')) {
                throw $this->alreadyApplied();
            }
            throw $e;
        }
    }

    /**
     * Link applicant documents (never copies of files) to an application.
     */
    public function attachDocuments(Application $application, array $documentIds, ?User $actor): void
    {
        $ids = collect($documentIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $owned = ApplicantDocument::where('applicant_id', $application->applicant_id)->whereIn('id', $ids)->pluck('id');

        if ($owned->count() !== $ids->count()) {
            throw ValidationException::withMessages(['document_ids' => ['Only the applicant\'s own documents can be attached.']]);
        }

        $application->documents()->syncWithoutDetaching($owned->mapWithKeys(fn ($id) => [$id => ['attached_by' => $actor?->id]])->all());
    }

    public function addNote(Application $application, string $body, User $actor): ApplicationNote
    {
        $employee = $actor->employee_id ? Employee::find($actor->employee_id) : null;

        return $application->notes()->create([
            'author_user_id' => $actor->id,
            'author_employee_id' => $employee?->id,
            'author_name' => $employee ? trim("{$employee->emp_first_name} {$employee->emp_last_name}") : $actor->username,
            'body' => $body,
        ]);
    }

    /**
     * Stage columns for a vacancy: each stage with its in-progress count, plus closed-out counts.
     *
     * @return array{stages: Collection, rejected: int, withdrawn: int}
     */
    public function pipeline(Vacancy $vacancy): array
    {
        $counts = Application::where('vacancy_id', $vacancy->id)
            ->whereIn('status', [Application::STATUS_ACTIVE, Application::STATUS_SHORTLISTED])
            ->selectRaw('current_vacancy_stage_id, count(*) as total')
            ->groupBy('current_vacancy_stage_id')
            ->pluck('total', 'current_vacancy_stage_id');

        $closed = Application::where('vacancy_id', $vacancy->id)
            ->whereIn('status', Application::TERMINAL)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'stages' => $vacancy->stages()->get(['id', 'name', 'stage_type', 'sort_order'])
                ->map(fn ($stage) => $stage->toArray() + ['count' => (int) ($counts[$stage->id] ?? 0)]),
            'rejected' => (int) ($closed[Application::STATUS_REJECTED] ?? 0),
            'withdrawn' => (int) ($closed[Application::STATUS_WITHDRAWN] ?? 0),
        ];
    }

    protected function alreadyApplied(): ValidationException
    {
        return ValidationException::withMessages(['applicant_id' => ['This applicant has already applied to this vacancy.']]);
    }
}
