<?php

namespace App\Services\Attendance;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceDay;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\Devices\Support\EventTime;
use App\Services\Attendance\Devices\Support\NormalizedEvent;
use App\Services\Attendance\Engine\AttendanceRecalculator;
use App\Services\Attendance\Engine\DayContextResolver;
use App\Services\Attendance\Ingestion\EventIngestionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Attendance corrections with a two-person approval:
 *   add_punch        -> on approval an "adjustment" event is ingested
 *   void_punch       -> on approval the event is excluded from calculation
 *   approve_overtime -> on approval N overtime minutes become payable
 *   mark_excused     -> on approval the day is excused (no absence/late/undertime)
 * The original values are captured at request time; raw events are never
 * changed; the requester can't decide their own request; locked days can't be
 * adjusted.
 */
class AttendanceAdjustmentService
{
    public function __construct(
        protected AttendanceScope $scope,
        protected EventIngestionService $ingestion,
        protected AttendanceRecalculator $recalculator,
        protected DayContextResolver $contexts,
        protected AttendanceAuditLogger $audit,
    ) {}

    public function getPaginatedAdjustments(User $user, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->scope->apply(AttendanceAdjustment::query(), $user)
            ->with(['employee:id,employee_number,emp_first_name,emp_last_name', 'requester:id,username', 'reviewer:id,username'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function request(Employee $employee, array $data, User $user): AttendanceAdjustment
    {
        if (! $this->scope->allows($user, $employee->id)) {
            abort(403);
        }
        $this->assertUnlocked($employee->id, $data['work_date']);

        $day = AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', $data['work_date'])->first();
        $corrected = match ($data['type']) {
            'add_punch' => ['occurred_at' => $data['occurred_at'] ?? null, 'event_type' => $data['event_type'] ?? 'unknown'],
            'approve_overtime' => ['minutes' => (int) ($data['minutes'] ?? 0)],
            default => null,
        };

        if ($data['type'] === 'void_punch') {
            $event = AttendanceEvent::where('id', $data['attendance_event_id'] ?? 0)->where('employee_id', $employee->id)->first()
                ?? throw ValidationException::withMessages(['attendance_event_id' => 'Choose one of this employee\'s punches.']);
        }
        if ($data['type'] === 'add_punch' && empty($data['occurred_at'])) {
            throw ValidationException::withMessages(['occurred_at' => 'Enter the missing punch time.']);
        }
        if ($data['type'] === 'approve_overtime' && ($corrected['minutes'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['minutes' => 'Enter the overtime minutes to approve.']);
        }

        $adjustment = AttendanceAdjustment::create([
            'employee_id' => $employee->id,
            'work_date' => $data['work_date'],
            'type' => $data['type'],
            'attendance_event_id' => $event->id ?? null,
            'original' => $day ? $day->only(['status', 'first_in', 'last_out', 'worked_minutes', 'late_minutes', 'undertime_minutes', 'overtime_minutes', 'approved_overtime_minutes']) + (isset($event) ? ['event' => ['id' => $event->id, 'occurred_at' => $event->occurred_at->toIso8601String(), 'type' => $event->event_type]] : []) : null,
            'corrected' => $corrected,
            'reason' => $data['reason'],
            'remarks' => $data['remarks'] ?? null,
            'status' => AttendanceAdjustment::STATUS_PENDING,
            'requested_by' => $user->id,
        ]);
        $this->audit->record($adjustment, 'requested', $user, ['type' => $adjustment->type, 'work_date' => $data['work_date']]);

        return $adjustment;
    }

    public function decide(AttendanceAdjustment $adjustment, bool $approve, User $user, ?string $remarks): AttendanceAdjustment
    {
        return DB::transaction(function () use ($adjustment, $approve, $user, $remarks) {
            $adjustment = AttendanceAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if ($adjustment->status !== AttendanceAdjustment::STATUS_PENDING) {
                throw ValidationException::withMessages(['adjustment' => 'This adjustment has already been decided.']);
            }
            if ((int) $adjustment->requested_by === $user->id) {
                throw ValidationException::withMessages(['adjustment' => 'You cannot decide an adjustment you requested.']);
            }
            if (! $this->scope->allows($user, $adjustment->employee_id)) {
                abort(403);
            }
            $this->assertUnlocked($adjustment->employee_id, $adjustment->work_date->toDateString());

            $createdEvent = null;
            if ($approve && $adjustment->type === 'add_punch') {
                $employee = Employee::findOrFail($adjustment->employee_id);
                $timezone = $this->contexts->resolve($employee, $adjustment->work_date->toDateString())->timezone;
                $raw = (string) $adjustment->corrected['occurred_at'];
                $this->ingestion->ingest(null, [new NormalizedEvent(null, 'adjustment:'.$adjustment->id, $raw, EventTime::toUtc($raw, $timezone), $adjustment->corrected['event_type'] ?? 'unknown', 'adjustment', ['adjustment_id' => $adjustment->id])],
                    'adjustment', null, $user, $employee->id, $timezone, $adjustment->reason, false);
                $createdEvent = AttendanceEvent::where('source', 'adjustment')->where('device_event_id', 'adjustment:'.$adjustment->id)->value('id');
            }

            $adjustment->update([
                'status' => $approve ? AttendanceAdjustment::STATUS_APPROVED : AttendanceAdjustment::STATUS_REJECTED,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
                'created_event_id' => $createdEvent,
            ]);
            $this->audit->record($adjustment, $approve ? 'approved' : 'rejected', $user, ['remarks' => $remarks]);

            if ($approve) {
                $this->recalculator->recalculateTouched([$adjustment->employee_id => [$adjustment->work_date->toDateString() => true]]);
                // The punch may fall on the next calendar day (overnight shift).
                $next = $adjustment->work_date->copy()->addDay()->toDateString();
                $this->recalculator->recalculateRange([$adjustment->employee_id], $next, $next);
            }

            return $adjustment;
        });
    }

    private function assertUnlocked(int $employeeId, string $date): void
    {
        if (AttendanceDay::where('employee_id', $employeeId)->whereDate('work_date', $date)->whereNotNull('locked_at')->exists()) {
            throw ValidationException::withMessages(['work_date' => 'This attendance day is locked.']);
        }
    }
}
