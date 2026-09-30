<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\EmployeeMovementType;
use App\Models\EmploymentStatus;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Employee movements: the employment events that change an employee's
 * current assignment, with a frozen before/after record of each.
 *
 * Rules:
 * - Every write locks the employee row first, so concurrent movements for the
 *   same employee are serialized and each sees the previous one's result.
 * - The "before" state is always read from that locked row, never from input.
 * - effective_date <= today: applied immediately. Later: stored as scheduled
 *   and applied on its date (daily command, or before the employee's next
 *   movement is recorded). Its before-state is captured when it applies.
 * - Movements are recorded in chronological order per employee.
 * - Nothing is deleted. Reversal ("cancel") restores the previous state only
 *   when it is safe to do so.
 */
class EmployeeMovementService
{
    public function getPaginatedMovements(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)
            ->with([
                'employee:id,employee_number,emp_first_name,emp_last_name',
                'type:id,code,name',
            ])
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The filtered list query, shared by the page and the export.
     */
    public function query(array $filters = []): Builder
    {
        return EmployeeMovement::query()
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $search = $filters['search'];
                $q->whereHas('employee', fn (Builder $e) => $e
                    ->where('emp_first_name', 'like', "%{$search}%")
                    ->orWhere('emp_last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%"));
            })
            ->when(! empty($filters['employee_id']), fn (Builder $q) => $q->where('employee_id', $filters['employee_id']))
            ->when(! empty($filters['movement_type_id']), fn (Builder $q) => $q->where('movement_type_id', $filters['movement_type_id']))
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->where(fn (Builder $d) => $d
                ->where('from_department_id', $filters['department_id'])
                ->orWhere('to_department_id', $filters['department_id'])))
            ->when(! empty($filters['employment_status_id']), fn (Builder $q) => $q->where('to_employment_status_id', $filters['employment_status_id']))
            ->when(! empty($filters['effective_from']), fn (Builder $q) => $q->whereDate('effective_date', '>=', $filters['effective_from']))
            ->when(! empty($filters['effective_to']), fn (Builder $q) => $q->whereDate('effective_date', '<=', $filters['effective_to']))
            ->orderByDesc('effective_date')
            ->orderByDesc('id');
    }

    /**
     * An employee's effective movements, newest first (self-service history).
     */
    public function getHistoryForEmployee(Employee $employee): Collection
    {
        return EmployeeMovement::with('type:id,code,name')
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeMovement::STATUS_EFFECTIVE)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The employee's current employment state, with display names.
     */
    public function currentState(Employee $employee): array
    {
        $state = $this->stateOf($employee);

        return ['values' => $state, 'labels' => $this->labels($state)];
    }

    /**
     * Record a movement for the employee in $data['employee_id'].
     *
     * @throws ValidationException for business-rule violations
     * @throws AuthorizationException when $actor may not move this employee
     */
    public function createMovement(array $data, User $actor, ?CarbonInterface $today = null): EmployeeMovement
    {
        $today = ($today ?? now())->toDateString();

        return DB::transaction(function () use ($data, $actor, $today) {
            $employee = Employee::whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('create', [EmployeeMovement::class, $employee]);

            $type = EmployeeMovementType::whereKey($data['movement_type_id'])->where('is_active', true)->first();

            if (! $type) {
                throw ValidationException::withMessages(['movement_type_id' => ['This movement type is not available.']]);
            }

            // Anything already due goes first, so the order below is real.
            $this->applyDueFor($employee, $today);

            $latest = $this->latestEffectiveDate($employee);

            if ($latest && $data['effective_date'] < $latest) {
                throw ValidationException::withMessages([
                    'effective_date' => ["The employee already has a movement effective {$latest}. Record movements in date order, or cancel the later movement first."],
                ]);
            }

            $changed = array_values(array_intersect(EmployeeMovement::FIELDS, $data['changed_fields'] ?? []));

            if (in_array('supervisor_id', $changed, true) && (int) ($data['to_supervisor_id'] ?? 0) === (int) $employee->id) {
                throw ValidationException::withMessages(['to_supervisor_id' => ['An employee cannot be their own supervisor.']]);
            }

            if ($changed === [] && $type->employee_status === null && ! empty($type->affected_fields)) {
                throw ValidationException::withMessages(['changed_fields' => ['Choose at least one change for this movement.']]);
            }

            $attributes = [
                'employee_id' => $employee->id,
                'movement_type_id' => $type->id,
                'effective_date' => $data['effective_date'],
                'reference_number' => $data['reference_number'] ?? null,
                'reason' => $data['reason'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'status' => EmployeeMovement::STATUS_SCHEDULED,
                'changed_fields' => $changed,
                'to_status' => $type->employee_status,
                'requested_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'created_by' => $actor->id,
            ];

            foreach ($changed as $field) {
                $attributes["to_{$field}"] = $data["to_{$field}"] ?? null;
            }

            $movement = EmployeeMovement::create($attributes);

            if ($data['effective_date'] <= $today) {
                $this->implement($movement, $employee);
            } else {
                $movement->update(['snapshot' => ['to' => $this->labels($this->requestedState($movement))]]);
            }

            return $movement->fresh(['type', 'employee']);
        });
    }

    /**
     * Apply every scheduled movement whose date has come (daily command).
     *
     * @return int number of movements applied
     */
    public function applyDue(?CarbonInterface $today = null): int
    {
        $today = ($today ?? now())->toDateString();

        $employeeIds = EmployeeMovement::where('status', EmployeeMovement::STATUS_SCHEDULED)
            ->whereDate('effective_date', '<=', $today)
            ->distinct()
            ->pluck('employee_id');

        $applied = 0;

        foreach ($employeeIds as $employeeId) {
            $applied += DB::transaction(function () use ($employeeId, $today) {
                $employee = Employee::whereKey($employeeId)->lockForUpdate()->first();

                return $employee ? $this->applyDueFor($employee, $today) : 0;
            });
        }

        return $applied;
    }

    /**
     * Only descriptive fields can be edited; employment data is corrected by
     * cancelling and re-recording, so history is never silently rewritten.
     */
    public function updateMovement(EmployeeMovement $movement, array $data): EmployeeMovement
    {
        $movement->update(array_intersect_key($data, array_flip(['reason', 'remarks', 'reference_number'])));

        return $movement;
    }

    /**
     * Withdraw a scheduled movement, or reverse an effective one.
     *
     * An effective movement is only reversed when it is the employee's latest
     * effective movement and the fields it changed still hold the values it
     * set; otherwise reversing would overwrite later changes.
     */
    public function cancelMovement(EmployeeMovement $movement, User $actor, string $reason): EmployeeMovement
    {
        return DB::transaction(function () use ($movement, $actor, $reason) {
            $employee = Employee::whereKey($movement->employee_id)->lockForUpdate()->firstOrFail();
            $movement = EmployeeMovement::whereKey($movement->id)->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('cancel', $movement);

            if ($movement->isEffective()) {
                $this->revert($movement, $employee);
            }

            $movement->update([
                'status' => EmployeeMovement::STATUS_CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $movement;
        });
    }

    /**
     * Must run inside a transaction holding the employee lock.
     */
    protected function applyDueFor(Employee $employee, string $today): int
    {
        $due = EmployeeMovement::where('employee_id', $employee->id)
            ->where('status', EmployeeMovement::STATUS_SCHEDULED)
            ->whereDate('effective_date', '<=', $today)
            ->orderBy('effective_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($due as $movement) {
            $this->implement($movement, $employee);
        }

        return $due->count();
    }

    /**
     * Apply a movement to the locked employee and freeze its before/after state.
     */
    protected function implement(EmployeeMovement $movement, Employee $employee): void
    {
        $before = $this->stateOf($employee);
        $after = $before;

        foreach ($movement->changed_fields ?? [] as $field) {
            $after[$field] = $movement->{"to_{$field}"};
        }

        if ($movement->to_status !== null) {
            $after['status'] = $movement->to_status;
        }

        $employee->update(array_diff_assoc($after, $before));

        $record = ['status' => EmployeeMovement::STATUS_EFFECTIVE, 'implemented_at' => now()];

        foreach ([...EmployeeMovement::FIELDS, 'status'] as $field) {
            $record["from_{$field}"] = $before[$field];
            $record["to_{$field}"] = $after[$field];
        }

        $record['snapshot'] = ['from' => $this->labels($before), 'to' => $this->labels($after)];

        $movement->update($record);
    }

    protected function revert(EmployeeMovement $movement, Employee $employee): void
    {
        $later = EmployeeMovement::where('employee_id', $employee->id)
            ->where('status', EmployeeMovement::STATUS_EFFECTIVE)
            ->whereKeyNot($movement->id)
            ->where(fn (Builder $q) => $q
                ->whereDate('effective_date', '>', $movement->effective_date)
                ->orWhere(fn (Builder $same) => $same
                    ->whereDate('effective_date', $movement->effective_date)
                    ->where('id', '>', $movement->id)))
            ->exists();

        if ($later) {
            throw ValidationException::withMessages([
                'movement' => ['A later movement is already in effect for this employee. Cancel the later movement first.'],
            ]);
        }

        $restore = [];

        foreach ([...EmployeeMovement::FIELDS, 'status'] as $field) {
            if ((string) $movement->{"from_{$field}"} === (string) $movement->{"to_{$field}"}) {
                continue;
            }

            if ((string) $employee->{$field} !== (string) $movement->{"to_{$field}"}) {
                throw ValidationException::withMessages([
                    'movement' => ["The employee's {$this->fieldLabel($field)} was changed after this movement, so it can't be reversed automatically."],
                ]);
            }

            $restore[$field] = $movement->{"from_{$field}"};
        }

        $employee->update($restore);
    }

    protected function latestEffectiveDate(Employee $employee): ?string
    {
        $date = EmployeeMovement::where('employee_id', $employee->id)
            ->where('status', EmployeeMovement::STATUS_EFFECTIVE)
            ->max('effective_date');

        return $date ? substr((string) $date, 0, 10) : null;
    }

    /**
     * @return array<string, mixed> the employee's employment fields and record status
     */
    protected function stateOf(Employee $employee): array
    {
        $state = [];

        foreach ([...EmployeeMovement::FIELDS, 'status'] as $field) {
            $state[$field] = $employee->{$field};
        }

        return $state;
    }

    /**
     * What a scheduled movement will set (changed fields only).
     */
    protected function requestedState(EmployeeMovement $movement): array
    {
        $state = [];

        foreach ($movement->changed_fields ?? [] as $field) {
            $state[$field] = $movement->{"to_{$field}"};
        }

        if ($movement->to_status !== null) {
            $state['status'] = $movement->to_status;
        }

        return $state;
    }

    /**
     * Display names for a state, frozen into the movement snapshot.
     */
    protected function labels(array $state): array
    {
        $labels = [];

        if (array_key_exists('department_id', $state)) {
            $labels['department'] = $state['department_id'] ? Department::whereKey($state['department_id'])->value('name') : null;
        }

        if (array_key_exists('job_title_id', $state)) {
            $labels['job_title'] = $state['job_title_id'] ? JobTitle::whereKey($state['job_title_id'])->value('job_title') : null;
        }

        if (array_key_exists('employment_status_id', $state)) {
            $labels['employment_status'] = $state['employment_status_id'] ? EmploymentStatus::whereKey($state['employment_status_id'])->value('name') : null;
        }

        if (array_key_exists('location_id', $state)) {
            $location = $state['location_id'] ? Location::find($state['location_id']) : null;
            $labels['location'] = $location ? trim(implode(', ', array_filter([$location->address, $location->city]))) : null;
        }

        if (array_key_exists('supervisor_id', $state)) {
            $supervisor = $state['supervisor_id'] ? Employee::find($state['supervisor_id']) : null;
            $labels['supervisor'] = $supervisor ? trim($supervisor->emp_first_name.' '.$supervisor->emp_last_name) : null;
        }

        if (array_key_exists('status', $state)) {
            $labels['status'] = $state['status'];
        }

        return $labels;
    }

    protected function fieldLabel(string $field): string
    {
        return match ($field) {
            'department_id' => 'department',
            'job_title_id' => 'job title',
            'employment_status_id' => 'employment status',
            'location_id' => 'location',
            'supervisor_id' => 'supervisor',
            default => 'status',
        };
    }
}
