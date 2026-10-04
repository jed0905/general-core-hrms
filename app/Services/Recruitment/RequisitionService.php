<?php

namespace App\Services\Recruitment;

use App\Models\JobRequisition;
use App\Models\JobRequisitionApproval;
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
 * Job requisitions: draft editing, submission and cancellation.
 * Approval decisions live in RequisitionApprovalService.
 */
class RequisitionService
{
    public const EDITABLE_FIELDS = [
        'department_id',
        'job_title_id',
        'location_id',
        'employment_status_id',
        'positions',
        'reason',
        'replaced_employee_id',
        'justification',
        'target_start_date',
        'requested_by_employee_id',
    ];

    public function __construct(
        protected NumberSequenceService $numberSequences,
        protected RequisitionApprovalService $approvals
    ) {}

    /**
     * Requisitions the user may see: everything with recruitment.requisition.view,
     * otherwise only ones they requested, created, or are an approver on.
     */
    public function visibleTo(User $user): Builder
    {
        return JobRequisition::query()->when(! $user->can('recruitment.requisition.view'), function (Builder $q) use ($user) {
            $q->where(function (Builder $w) use ($user) {
                $w->where('created_by', $user->id);
                if ($user->employee_id) {
                    $w->orWhere('requested_by_employee_id', $user->employee_id)
                        ->orWhereHas('approvals', fn ($a) => $a->where('approver_id', $user->employee_id));
                }
            });
        });
    }

    public function getPaginatedRequisitions(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->visibleTo($user)
            ->with(['department:id,name', 'jobTitle:id,job_title', 'requestedBy:id,emp_first_name,emp_last_name'])
            ->withSum(['vacancies as allocated_openings' => fn ($q) => $q->where('status', '!=', Vacancy::STATUS_CANCELLED)], 'openings')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('requisition_number', 'like', "%{$search}%")
                ->orWhereHas('jobTitle', fn ($j) => $j->where('job_title', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createRequisition(array $data, User $actor): JobRequisition
    {
        return DB::transaction(function () use ($data, $actor) {
            $number = $this->numberSequences->next(
                NumberSequence::JOB_REQUISITION_NUMBER,
                fn (string $candidate) => JobRequisition::where('requisition_number', $candidate)->exists()
            );

            return JobRequisition::create($this->normalize(Arr::only($data, self::EDITABLE_FIELDS)) + [
                'requisition_number' => $number,
                'status' => JobRequisition::STATUS_DRAFT,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function updateRequisition(JobRequisition $requisition, array $data): JobRequisition
    {
        return DB::transaction(function () use ($requisition, $data) {
            $requisition = $this->lock($requisition);
            $this->assertStatus($requisition, [JobRequisition::STATUS_DRAFT], 'Only draft requisitions can be edited.');

            $requisition->update($this->normalize(Arr::only($data, self::EDITABLE_FIELDS)));

            return $requisition;
        });
    }

    public function submit(JobRequisition $requisition, User $actor): JobRequisition
    {
        return DB::transaction(function () use ($requisition) {
            $requisition = $this->lock($requisition);
            $this->assertStatus($requisition, [JobRequisition::STATUS_DRAFT], 'Only draft requisitions can be submitted.');

            $this->approvals->createApprovals($requisition);

            $requisition->update(['status' => JobRequisition::STATUS_PENDING, 'submitted_at' => now()]);

            return $requisition->fresh();
        });
    }

    public function cancel(JobRequisition $requisition, User $actor, string $reason): JobRequisition
    {
        return DB::transaction(function () use ($requisition, $actor, $reason) {
            $requisition = $this->lock($requisition);
            $this->assertStatus($requisition, JobRequisition::CANCELLABLE, 'This requisition can no longer be cancelled.');

            if ($requisition->isApproved() && $requisition->vacancies()->where('status', '!=', Vacancy::STATUS_CANCELLED)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['status' => ['Cancel the vacancies created from this requisition first.']]);
            }

            $requisition->approvals()
                ->where('status', JobRequisitionApproval::STATUS_PENDING)
                ->update(['status' => JobRequisitionApproval::STATUS_SKIPPED]);

            $requisition->update([
                'status' => JobRequisition::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ]);

            return $requisition->fresh();
        });
    }

    protected function lock(JobRequisition $requisition): JobRequisition
    {
        return JobRequisition::whereKey($requisition->id)->lockForUpdate()->firstOrFail();
    }

    protected function assertStatus(JobRequisition $requisition, array $allowed, string $message): void
    {
        if (! in_array($requisition->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => [$message]]);
        }
    }

    /**
     * A replaced employee only makes sense for replacement requisitions.
     */
    protected function normalize(array $data): array
    {
        if (array_key_exists('reason', $data) && $data['reason'] !== JobRequisition::REASON_REPLACEMENT) {
            $data['replaced_employee_id'] = null;
        }

        return $data;
    }
}
