<?php

namespace App\Services\Recruitment;

use App\Models\Application;
use App\Models\ApplicationInterview;
use App\Models\Employee;
use App\Models\InterviewPanelist;
use App\Models\InterviewReschedule;
use App\Models\InterviewType;
use App\Models\RecruitmentStage;
use App\Models\User;
use App\Models\VacancyStage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Interview scheduling, panel assignment, rescheduling, cancellation and
 * completion. Never changes the application's stage (that is
 * ApplicationPipelineService's job).
 *
 * Double booking: the panelists' employee rows are locked (in id order, so two
 * schedulers can't deadlock) before the overlap check, so two simultaneous
 * bookings of the same person are serialized and the second sees the first.
 * Only scheduled interviews block a time slot.
 *
 * Lock order is application → interview → employees, and every lock is taken
 * BEFORE the first plain read: on MySQL/MariaDB (REPEATABLE READ) the first
 * plain read fixes the transaction's snapshot, so reading earlier would hide an
 * interview committed by the transaction we were waiting on.
 *
 * Status rules: scheduled → completed | cancelled. Completed and cancelled are final.
 */
class InterviewService
{
    public function __construct(protected ApplicationPipelineService $pipeline) {}

    /**
     * @param  array{interview_type_id: int, mode: string, scheduled_date: string, start_time: string, duration_minutes: int, location?: ?string, meeting_url?: ?string, instructions?: ?string, panelist_ids: array<int>, primary_panelist_id?: ?int}  $data
     */
    public function schedule(Application $application, array $data, User $actor): ApplicationInterview
    {
        return DB::transaction(function () use ($application, $data, $actor) {
            $application = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $panelistIds = $this->lockPanelists($data['panelist_ids']);

            $this->assertApplicationAcceptsInterviews($application);
            $this->assertActiveType((int) $data['interview_type_id']);

            [$startsAt, $endsAt] = $this->window($data);
            $this->assertNoOverlap($application, $panelistIds, $startsAt, $endsAt);

            $interview = ApplicationInterview::create($this->details($data) + [
                'application_id' => $application->id,
                'round' => (int) ApplicationInterview::where('application_id', $application->id)->max('round') + 1,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => (int) $data['duration_minutes'],
                'status' => ApplicationInterview::STATUS_SCHEDULED,
                'created_by' => $actor->id,
            ]);

            $this->syncPanelists($interview, $panelistIds, empty($data['primary_panelist_id']) ? null : (int) $data['primary_panelist_id']);
            $application->update(['last_activity_at' => now()]);

            return $interview;
        });
    }

    /**
     * Change type, mode, place, instructions or panel of a scheduled interview
     * (times change through reschedule()).
     */
    public function update(ApplicationInterview $interview, array $data, User $actor): ApplicationInterview
    {
        return DB::transaction(function () use ($interview, $data, $actor) {
            [$application, $interview] = $this->lockScheduled($interview);
            $panelistIds = $this->lockPanelists($data['panelist_ids']);

            $this->assertApplicationAcceptsInterviews($application);
            if ((int) $data['interview_type_id'] !== $interview->interview_type_id) {
                $this->assertActiveType((int) $data['interview_type_id']);
            }

            $this->assertNoOverlap($application, $panelistIds, $interview->starts_at, $interview->ends_at, $interview->id);

            $interview->update($this->details($data) + ['updated_by' => $actor->id]);
            $this->syncPanelists($interview, $panelistIds, empty($data['primary_panelist_id']) ? null : (int) $data['primary_panelist_id']);

            return $interview;
        });
    }

    /**
     * New date/time for a scheduled interview. The previous times are kept in interview_reschedules.
     *
     * @param  array{scheduled_date: string, start_time: string, duration_minutes: int, reason?: ?string}  $data
     */
    public function reschedule(ApplicationInterview $interview, array $data, User $actor): ApplicationInterview
    {
        // The panel is read before the transaction so its rows can be locked first; it is re-checked below.
        $panel = $this->panelOf($interview);

        return DB::transaction(function () use ($interview, $data, $actor, $panel) {
            [$application, $interview] = $this->lockScheduled($interview);
            $panelistIds = $this->lockPanelists($panel->all());

            if ($this->panelOf($interview)->values()->all() !== $panelistIds->values()->all()) {
                throw ValidationException::withMessages(['status' => ['The interview panel just changed. Refresh and try again.']]);
            }
            $this->assertApplicationAcceptsInterviews($application);

            [$startsAt, $endsAt] = $this->window($data);
            if ($startsAt->equalTo($interview->starts_at) && $endsAt->equalTo($interview->ends_at)) {
                throw ValidationException::withMessages(['scheduled_date' => ['Choose a different date or time to reschedule.']]);
            }

            $this->assertNoOverlap($application, $panelistIds, $startsAt, $endsAt, $interview->id);

            InterviewReschedule::create([
                'application_interview_id' => $interview->id,
                'previous_starts_at' => $interview->starts_at,
                'previous_ends_at' => $interview->ends_at,
                'new_starts_at' => $startsAt,
                'new_ends_at' => $endsAt,
                'reason' => $data['reason'] ?? null,
                'rescheduled_by' => $actor->id,
                'rescheduled_at' => now(),
            ]);

            $interview->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => (int) $data['duration_minutes'],
                'updated_by' => $actor->id,
            ]);

            return $interview;
        });
    }

    /**
     * Allowed even after the application was rejected or withdrawn, so its
     * panel's time is released.
     */
    public function cancel(ApplicationInterview $interview, ?string $reason, User $actor): ApplicationInterview
    {
        return DB::transaction(function () use ($interview, $reason, $actor) {
            [, $interview] = $this->lockScheduled($interview);

            $interview->update([
                'status' => ApplicationInterview::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
                'updated_by' => $actor->id,
            ]);

            return $interview;
        });
    }

    public function complete(ApplicationInterview $interview, ?string $remarks, User $actor): ApplicationInterview
    {
        return DB::transaction(function () use ($interview, $remarks, $actor) {
            [$application, $interview] = $this->lockScheduled($interview);

            if ($application->isTerminal()) {
                throw ValidationException::withMessages(['status' => ["This application is {$application->status}; cancel the interview instead."]]);
            }
            if ($interview->starts_at->isFuture()) {
                throw ValidationException::withMessages(['status' => ['An interview can only be marked completed once it has started.']]);
            }

            $interview->update([
                'status' => ApplicationInterview::STATUS_COMPLETED,
                'completed_at' => now(),
                'completed_by' => $actor->id,
                'completion_remarks' => $remarks,
                'updated_by' => $actor->id,
            ]);
            $application->update(['last_activity_at' => now()]);

            return $interview;
        });
    }

    /**
     * Interviews where the employee sits on the panel (My Interviews).
     */
    public function getPaginatedForPanelist(int $employeeId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $scope = $filters['scope'] ?? 'upcoming';

        return ApplicationInterview::query()
            ->whereHas('panelists', fn ($q) => $q->where('employee_id', $employeeId))
            ->with([
                'type:id,name',
                'application:id,application_number,applicant_id,vacancy_id',
                'application.applicant:id,first_name,last_name',
                'application.vacancy:id,vacancy_number,title',
                'evaluations' => fn ($q) => $q->where('evaluator_employee_id', $employeeId)->select(['id', 'application_interview_id', 'status']),
            ])
            ->when($scope === 'upcoming', fn ($q) => $q->where('status', ApplicationInterview::STATUS_SCHEDULED))
            ->when(in_array($scope, [ApplicationInterview::STATUS_COMPLETED, ApplicationInterview::STATUS_CANCELLED], true), fn ($q) => $q->where('status', $scope))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('starts_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('starts_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($scope === 'upcoming', fn ($q) => $q->orderBy('starts_at'), fn ($q) => $q->orderByDesc('starts_at'))
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Submitted / expected scorecards per interview, e.g. "2 of 3".
     *
     * @return array{panelists: int, submitted: int, drafts: int}
     */
    public function evaluationProgress(ApplicationInterview $interview): array
    {
        $statuses = $interview->evaluations()->pluck('status');

        return [
            'panelists' => $interview->panelists()->count(),
            'submitted' => $statuses->filter(fn ($s) => $s === 'submitted')->count(),
            'drafts' => $statuses->filter(fn ($s) => $s === 'draft')->count(),
        ];
    }

    // ----------------------------------------------------------------- internals

    /**
     * Interviews can be scheduled once the application is in Interview or a later
     * phase 3 stage, while it is still in progress and the vacancy isn't frozen.
     */
    protected function assertApplicationAcceptsInterviews(Application $application): void
    {
        if ($application->isTerminal()) {
            throw ValidationException::withMessages(['status' => ["This application is {$application->status}; no interviews can be scheduled."]]);
        }

        $type = VacancyStage::whereKey($application->current_vacancy_stage_id)->value('stage_type');
        if (! in_array($type, RecruitmentStage::POST_SHORTLIST_TYPES, true)) {
            throw ValidationException::withMessages(['status' => ['Move the application to the Interview stage before scheduling interviews.']]);
        }

        $this->pipeline->assertVacancyAcceptsProgress($application);
    }

    protected function assertActiveType(int $typeId): void
    {
        if (! InterviewType::whereKey($typeId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['interview_type_id' => ['Choose an active interview type.']]);
        }
    }

    /**
     * @return array{0: Application, 1: ApplicationInterview}
     */
    protected function lockScheduled(ApplicationInterview $interview): array
    {
        // Application first, then the interview: the same order as schedule().
        $application = Application::whereKey($interview->application_id)->lockForUpdate()->firstOrFail();
        $interview = ApplicationInterview::whereKey($interview->id)->lockForUpdate()->firstOrFail();

        if (! $interview->isScheduled()) {
            throw ValidationException::withMessages(['status' => ["This interview is already {$interview->status}."]]);
        }

        return [$application, $interview];
    }

    /** @return Collection<int, int> sorted employee ids */
    protected function panelOf(ApplicationInterview $interview): Collection
    {
        return InterviewPanelist::where('application_interview_id', $interview->id)->orderBy('employee_id')->pluck('employee_id')->map(fn ($id) => (int) $id);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function window(array $data): array
    {
        $startsAt = Carbon::createFromFormat('Y-m-d H:i', "{$data['scheduled_date']} {$data['start_time']}")->startOfMinute();

        return [$startsAt, $startsAt->copy()->addMinutes((int) $data['duration_minutes'])];
    }

    /**
     * Lock the panelists' employee rows (id order) and require current employees.
     *
     * @return Collection<int, int>
     */
    protected function lockPanelists(array $employeeIds): Collection
    {
        $ids = collect($employeeIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();

        $found = Employee::whereIn('id', $ids)->whereNotIn('status', ['archived', 'terminated'])
            ->orderBy('id')->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id);

        if ($ids->isEmpty() || $found->count() !== $ids->count()) {
            throw ValidationException::withMessages(['panelist_ids' => ['Choose one or more current employees as panelists.']]);
        }

        return $found;
    }

    protected function assertNoOverlap(Application $application, Collection $panelistIds, Carbon $startsAt, Carbon $endsAt, ?int $ignoreInterviewId = null): void
    {
        $overlapping = fn () => ApplicationInterview::query()
            ->where('status', ApplicationInterview::STATUS_SCHEDULED)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($ignoreInterviewId, fn ($q, $id) => $q->whereKeyNot($id));

        $busy = InterviewPanelist::whereIn('employee_id', $panelistIds)
            ->whereIn('application_interview_id', $overlapping()->select('id'))
            ->with('employee:id,emp_first_name,emp_last_name')
            ->get()
            ->map(fn ($p) => "{$p->employee->emp_first_name} {$p->employee->emp_last_name}")
            ->unique();

        if ($busy->isNotEmpty()) {
            throw ValidationException::withMessages(['panelist_ids' => ['Already booked for an overlapping interview: '.$busy->implode(', ').'.']]);
        }

        if ($overlapping()->where('application_id', $application->id)->exists()) {
            throw ValidationException::withMessages(['start_time' => ['This applicant already has an interview at that time.']]);
        }
    }

    protected function details(array $data): array
    {
        $mode = $data['mode'];

        return [
            'interview_type_id' => (int) $data['interview_type_id'],
            'mode' => $mode,
            'location' => $mode === ApplicationInterview::MODE_VIDEO ? null : ($data['location'] ?? null),
            'meeting_url' => $mode === ApplicationInterview::MODE_VIDEO ? ($data['meeting_url'] ?? null) : null,
            'instructions' => $data['instructions'] ?? null,
        ];
    }

    protected function syncPanelists(ApplicationInterview $interview, Collection $panelistIds, ?int $primaryId): void
    {
        $primaryId = $primaryId && $panelistIds->contains($primaryId) ? $primaryId : $panelistIds->first();

        InterviewPanelist::where('application_interview_id', $interview->id)->whereNotIn('employee_id', $panelistIds)->delete();

        foreach ($panelistIds as $employeeId) {
            InterviewPanelist::updateOrCreate(
                ['application_interview_id' => $interview->id, 'employee_id' => $employeeId],
                ['is_primary' => $employeeId === $primaryId]
            );
        }
    }
}
