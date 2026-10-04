<?php

namespace App\Services\Recruitment;

use App\Models\JobRequisition;
use App\Models\JobTitle;
use App\Models\NumberSequence;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\NumberSequenceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vacancies: creation (optionally from an approved requisition), content
 * edits and guarded status transitions. Status never changes outside transition().
 */
class VacancyService
{
    /** Copied from the requisition when a vacancy originates from one, and then fixed. */
    public const POSITION_FIELDS = ['job_title_id', 'department_id', 'location_id', 'employment_status_id'];

    /** What an assigned hiring manager (without vacancy.update) may edit. */
    public const CONTENT_FIELDS = ['description', 'responsibilities', 'qualifications'];

    public const EDITABLE_FIELDS = [
        'title',
        'job_title_id',
        'department_id',
        'location_id',
        'employment_status_id',
        'openings',
        'description',
        'responsibilities',
        'qualifications',
        'salary_min',
        'salary_max',
        'salary_currency',
        'opening_date',
        'closing_date',
        'visibility',
        'hiring_manager_id',
    ];

    public function __construct(
        protected NumberSequenceService $numberSequences,
        protected VacancyStageService $stages
    ) {}

    /**
     * All vacancies with recruitment.vacancy.view; otherwise only those the user is hiring manager for.
     */
    public function visibleTo(User $user): Builder
    {
        return Vacancy::query()->when(! $user->can('recruitment.vacancy.view'), fn (Builder $q) => $user->employee_id
            ? $q->where('hiring_manager_id', $user->employee_id)
            : $q->whereRaw('1 = 0'));
    }

    public function getPaginatedVacancies(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->visibleTo($user)
            ->with(['department:id,name', 'jobTitle:id,job_title', 'location:id,address,city', 'hiringManager:id,emp_first_name,emp_last_name', 'requisition:id,requisition_number'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('vacancy_number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createVacancy(array $data, User $actor): Vacancy
    {
        return DB::transaction(function () use ($data, $actor) {
            $attributes = Arr::only($data, self::EDITABLE_FIELDS);

            if (! empty($data['job_requisition_id'])) {
                $requisition = JobRequisition::whereKey($data['job_requisition_id'])->lockForUpdate()->firstOrFail();

                if (! $requisition->isApproved()) {
                    throw ValidationException::withMessages(['job_requisition_id' => ['Vacancies can only be created from an approved requisition.']]);
                }

                $this->assertWithinRequisition($requisition, (int) $attributes['openings']);

                // The vacancy keeps its own copy of the approved position data.
                $attributes = array_merge($attributes, $requisition->only(self::POSITION_FIELDS), ['job_requisition_id' => $requisition->id]);
            }

            $attributes['title'] = trim((string) ($attributes['title'] ?? '')) ?: JobTitle::whereKey($attributes['job_title_id'])->value('job_title');

            $number = $this->numberSequences->next(
                NumberSequence::VACANCY_NUMBER,
                fn (string $candidate) => Vacancy::where('vacancy_number', $candidate)->exists()
            );

            return Vacancy::create($attributes + [
                'vacancy_number' => $number,
                'filled_count' => 0,
                'status' => Vacancy::STATUS_DRAFT,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * @param  bool  $contentOnly  hiring-manager edit: description, responsibilities, qualifications only
     */
    public function updateVacancy(Vacancy $vacancy, array $data, bool $contentOnly = false): Vacancy
    {
        return DB::transaction(function () use ($vacancy, $data, $contentOnly) {
            $vacancy = Vacancy::whereKey($vacancy->id)->lockForUpdate()->firstOrFail();

            if ($vacancy->isTerminal()) {
                throw ValidationException::withMessages(['status' => ['Closed, filled or cancelled vacancies cannot be edited.']]);
            }

            $attributes = Arr::only($data, $contentOnly ? self::CONTENT_FIELDS : self::EDITABLE_FIELDS);

            if ($vacancy->job_requisition_id) {
                // Approved position data is fixed for requisition-based vacancies.
                $attributes = Arr::except($attributes, self::POSITION_FIELDS);
            }

            if (array_key_exists('openings', $attributes)) {
                $openings = (int) $attributes['openings'];

                if ($openings < $vacancy->filled_count) {
                    throw ValidationException::withMessages(['openings' => ["Openings cannot be fewer than the {$vacancy->filled_count} already filled."]]);
                }

                if ($vacancy->job_requisition_id && $openings !== $vacancy->openings) {
                    $requisition = JobRequisition::whereKey($vacancy->job_requisition_id)->lockForUpdate()->firstOrFail();
                    $this->assertWithinRequisition($requisition, $openings, $vacancy->id);
                }
            }

            if (array_key_exists('title', $attributes) && trim((string) $attributes['title']) === '') {
                unset($attributes['title']);
            }

            $vacancy->update($attributes);

            return $vacancy;
        });
    }

    /**
     * The only way a vacancy's status changes. Locks the row and re-checks the transition.
     */
    public function transition(Vacancy $vacancy, string $to, User $actor, ?string $reason = null): Vacancy
    {
        return DB::transaction(function () use ($vacancy, $to, $reason) {
            $vacancy = Vacancy::whereKey($vacancy->id)->lockForUpdate()->firstOrFail();

            if (! $vacancy->canTransitionTo($to)) {
                throw ValidationException::withMessages(['status' => ["A vacancy cannot move from {$this->label($vacancy->status)} to {$this->label($to)}."]]);
            }

            $changes = ['status' => $to, 'status_reason' => $reason];

            if ($to === Vacancy::STATUS_OPEN) {
                // First opening fixes the vacancy's own copy of the recruitment pipeline.
                $this->stages->snapshot($vacancy);
                $changes['opened_at'] = $vacancy->opened_at ?? now();
                $changes['opening_date'] = $vacancy->opening_date ?? now()->toDateString();
                $changes['closed_at'] = null;
            }

            if (in_array($to, Vacancy::TERMINAL, true)) {
                $changes['closed_at'] = now();
            }

            $vacancy->update($changes);

            return $vacancy;
        });
    }

    /**
     * Openings across a requisition's active vacancies may not exceed its approved positions.
     */
    protected function assertWithinRequisition(JobRequisition $requisition, int $openings, ?int $ignoreVacancyId = null): void
    {
        $allocated = (int) $requisition->vacancies()
            ->where('status', '!=', Vacancy::STATUS_CANCELLED)
            ->when($ignoreVacancyId, fn ($q) => $q->whereKeyNot($ignoreVacancyId))
            ->sum('openings');

        $remaining = $requisition->positions - $allocated;

        if ($openings > $remaining) {
            throw ValidationException::withMessages(['openings' => ["Only {$remaining} of the requisition's {$requisition->positions} approved positions remain unallocated."]]);
        }
    }

    protected function label(string $status): string
    {
        return str_replace('_', ' ', $status);
    }
}
