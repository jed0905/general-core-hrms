<?php

namespace App\Services\Attendance\Engine;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceDay;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * Computes one employee's attendance for one work date from raw events and
 * approved adjustments. Vendor-independent; raw events are only read.
 *
 * Steps: schedule & window -> events (accepted, not voided, plus approved
 * added punches) -> debounce -> pairing (in_out or first_last) -> worked time
 * (minus the scheduled break when there are no break punches) -> late,
 * undertime, overtime (approved when the policy requires it), night time ->
 * minute buckets by (day type, holiday type, overtime, night) -> status.
 * All interval math is on UTC instants (overnight shifts and DST safe).
 */
class AttendanceCalculator
{
    private const IN = ['check_in', 'break_in', 'overtime_in'];

    private const OUT = ['check_out', 'break_out', 'overtime_out'];

    public function __construct(protected DayContextResolver $contexts) {}

    public function compute(Employee $employee, string $date): ?AttendanceDay
    {
        $existing = AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', $date)->first();
        if ($existing?->locked_at) {
            return $existing;
        }

        $context = $this->contexts->resolve($employee, $date);
        [$windowFrom, $windowTo] = $context->window();
        $adjustments = AttendanceAdjustment::where('employee_id', $employee->id)->where('status', AttendanceAdjustment::STATUS_APPROVED)->get();
        $voided = $adjustments->where('type', 'void_punch')->pluck('attendance_event_id')->filter()->all();

        $events = AttendanceEvent::where('employee_id', $employee->id)
            ->where('status', AttendanceEvent::STATUS_ACCEPTED)
            ->where('occurred_at', '>=', $windowFrom)
            ->where('occurred_at', '<', $windowTo)
            ->whereNotIn('id', $voided)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['id', 'occurred_at', 'event_type']);
        $events = $this->debounce($events->all(), (int) ($context->policy->debounce_minutes ?? 0));

        $exceptions = [];
        $pairs = $this->pair($events, $context->policy->pairing_mode ?? 'in_out', $exceptions);
        $hasBreakPunches = collect($events)->contains(fn ($e) => str_starts_with($e->event_type, 'break_'));
        if (($context->policy->deduct_scheduled_break ?? true) && ! $hasBreakPunches && ($break = $context->scheduledBreak())) {
            $pairs = $this->subtract($pairs, $break);
        }

        $worked = $this->minutes($pairs);
        $start = $context->scheduledStart();
        $end = $context->scheduledEnd();
        $dayType = $context->dayType();
        $flexible = (bool) $context->shift?->is_flexible;
        $required = $context->requiredMinutes();
        $leaveMinutes = (int) round((float) $context->leaveDays * $required);
        $firstIn = $pairs ? $pairs[0][0] : ($events ? $events[0]->occurred_at : null);
        $lastOut = $pairs ? end($pairs)[1] : null;

        // Lateness and undertime apply to scheduled workdays only.
        $late = $undertime = 0;
        if ($dayType === 'workday' && $pairs) {
            if ($flexible) {
                $undertime = max(0, $required - $worked);
            } else {
                // Gaps are measured in scheduled working time: the unpaid break doesn't count.
                $break = $context->scheduledBreak();
                $lateBy = $firstIn->greaterThan($start) ? $this->minutes($break ? $this->subtract([[$start, $firstIn]], $break) : [[$start, $firstIn]]) : 0;
                $late = $lateBy > (int) $context->policy->late_grace_minutes ? $lateBy : 0;
                $earlyBy = $lastOut->lessThan($end) ? $this->minutes($break ? $this->subtract([[$lastOut, $end]], $break) : [[$lastOut, $end]]) : 0;
                $undertime = $earlyBy > (int) $context->policy->undertime_grace_minutes ? $earlyBy : 0;
            }
            // Approved partial leave covers missing time first.
            $covered = min($undertime, $leaveMinutes);
            $undertime -= $covered;
            $late = max(0, $late - ($leaveMinutes - $covered));
        }

        $overtime = $this->overtime($context, $pairs, $worked, $required, $dayType, $start, $end);
        $approvedOvertime = ($context->policy->overtime_requires_approval ?? true)
            ? min($overtime, (int) $adjustments->where('type', 'approve_overtime')->filter(fn ($a) => $a->work_date->toDateString() === $date)->sum(fn ($a) => (int) ($a->corrected['minutes'] ?? 0)))
            : $overtime;

        $nightWindows = $context->nightWindows();
        $buckets = $this->buckets($pairs, $worked - $overtime, $approvedOvertime, $nightWindows, $dayType, $context->holiday?->type);
        $night = (int) array_sum(array_map(fn ($k, $v) => str_ends_with($k, '|1') ? $v : 0, array_keys($buckets), $buckets));

        $excused = $adjustments->where('type', 'mark_excused')->contains(fn ($a) => $a->work_date->toDateString() === $date);
        [$status, $absence] = $this->status($dayType, $events, $exceptions, $late, (float) $context->leaveDays, $excused);
        if ($excused) {
            $late = $undertime = 0;
        }

        return AttendanceDay::updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $date],
            [
                'timezone' => $context->timezone,
                'day_type' => $dayType,
                'holiday_type' => $context->holiday?->type,
                'holiday_id' => $context->holiday?->id,
                'shift_id' => $context->shift?->id,
                'scheduled_start' => $start?->utc(),
                'scheduled_end' => $end?->utc(),
                'scheduled_minutes' => $dayType === 'workday' ? $required : 0,
                'first_in' => $firstIn?->utc(),
                'last_out' => $lastOut?->utc(),
                'worked_minutes' => $worked,
                'regular_minutes' => max(0, $worked - $overtime),
                'late_minutes' => $late,
                'undertime_minutes' => $undertime,
                'overtime_minutes' => $overtime,
                'approved_overtime_minutes' => $approvedOvertime,
                'night_minutes' => $night,
                'leave_days' => $context->leaveDays,
                'absence_days' => $absence,
                'status' => $status,
                'buckets' => $buckets,
                'exceptions' => $exceptions ?: null,
                'event_ids' => array_map(fn ($e) => $e->id, $events),
                'pairs' => array_map(fn ($p) => [$p[0]->toIso8601String(), $p[1]->toIso8601String()], $pairs),
                'attendance_policy_id' => $context->policy->id,
                'computed_at' => now(),
            ]
        );
    }

    /** Drop a punch of the same type within $minutes of the previous kept punch (double taps). */
    private function debounce(array $events, int $minutes): array
    {
        $kept = [];
        foreach ($events as $event) {
            $last = end($kept);
            if ($last && $minutes > 0 && $last->event_type === $event->event_type
                && $last->occurred_at->diffInSeconds($event->occurred_at) < $minutes * 60) {
                continue;
            }
            $kept[] = $event;
        }

        return $kept;
    }

    /** @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}> */
    private function pair(array $events, string $mode, array &$exceptions): array
    {
        if ($mode === 'first_last') {
            if (count($events) === 1) {
                $exceptions[] = ['code' => 'missing_punch', 'message' => 'Only one punch recorded.'];

                return [];
            }

            return $events ? [[$events[0]->occurred_at, end($events)->occurred_at]] : [];
        }

        $pairs = [];
        $open = null;
        foreach ($events as $event) {
            $direction = in_array($event->event_type, self::IN, true) ? 'in'
                : (in_array($event->event_type, self::OUT, true) ? 'out' : ($open ? 'out' : 'in'));

            if ($direction === 'in') {
                if ($open) {
                    $exceptions[] = ['code' => 'missing_out', 'message' => 'No out punch after '.$open->occurred_at->toIso8601String().'.'];
                }
                $open = $event;
            } elseif ($open) {
                $pairs[] = [$open->occurred_at, $event->occurred_at];
                $open = null;
            } else {
                $exceptions[] = ['code' => 'missing_in', 'message' => 'Out punch at '.$event->occurred_at->toIso8601String().' has no in punch.'];
            }
        }
        if ($open) {
            $exceptions[] = ['code' => 'missing_out', 'message' => 'No out punch after '.$open->occurred_at->toIso8601String().'.'];
        }

        return $pairs;
    }

    /** Remove an interval (e.g. the scheduled break) from the pairs. */
    private function subtract(array $pairs, array $gap): array
    {
        [$gapFrom, $gapTo] = $gap;
        $result = [];
        foreach ($pairs as [$from, $to]) {
            if ($to->lessThanOrEqualTo($gapFrom) || $from->greaterThanOrEqualTo($gapTo)) {
                $result[] = [$from, $to];

                continue;
            }
            if ($from->lessThan($gapFrom)) {
                $result[] = [$from, $gapFrom];
            }
            if ($to->greaterThan($gapTo)) {
                $result[] = [$gapTo, $to];
            }
        }

        return $result;
    }

    private function minutes(array $pairs): int
    {
        return (int) floor(array_sum(array_map(fn ($p) => max(0, $p[0]->diffInSeconds($p[1], false)), $pairs)) / 60);
    }

    private function overtime(DayContext $context, array $pairs, int $worked, int $required, string $dayType, ?CarbonImmutable $start, ?CarbonImmutable $end): int
    {
        $threshold = (int) ($context->policy->overtime_threshold_minutes ?? 0);

        if ($dayType === 'workday' && $start && $end && ! $context->shift->is_flexible) {
            $after = $this->minutes($this->subtract($pairs, [CarbonImmutable::createFromTimestamp(0), $end]));
            $before = ($context->policy->count_early_arrival_as_overtime ?? false)
                ? $this->minutes($this->subtract($pairs, [$start, CarbonImmutable::createFromTimestamp(4102444800)]))
                : 0;
            $overtime = $after + $before;
        } else {
            // Flexible shifts, rest days and holidays: time beyond the shift's required minutes.
            $overtime = $required > 0 ? max(0, $worked - $required) : 0;
        }

        return $overtime >= max(1, $threshold) ? $overtime : 0;
    }

    /**
     * Paid minutes by bucket "dayType|holidayType|overtime(0/1)|night(0/1)".
     * The first $regular worked minutes are regular, the next $approvedOvertime
     * are overtime; unapproved overtime is not bucketed (not paid).
     */
    private function buckets(array $pairs, int $regular, int $approvedOvertime, array $nightWindows, string $dayType, ?string $holidayType): array
    {
        $buckets = [];
        $used = 0;
        foreach ($pairs as [$from, $to]) {
            $cursor = $from;
            while ($cursor->lessThan($to)) {
                $isOvertime = $used >= $regular;
                if ($isOvertime && $used >= $regular + $approvedOvertime) {
                    break 2;
                }
                $limit = $isOvertime ? $regular + $approvedOvertime : $regular;
                $segmentEnd = $cursor->addMinutes($limit - $used)->min($to);
                foreach ($nightWindows as [$nFrom, $nTo]) {
                    foreach ([$nFrom, $nTo] as $boundary) {
                        if ($boundary->greaterThan($cursor) && $boundary->lessThan($segmentEnd)) {
                            $segmentEnd = $boundary;
                        }
                    }
                }
                $minutes = (int) floor($cursor->diffInSeconds($segmentEnd) / 60);
                if ($minutes <= 0) {
                    $cursor = $segmentEnd->greaterThan($cursor) ? $segmentEnd : $cursor->addMinute();

                    continue;
                }
                $isNight = collect($nightWindows)->contains(fn ($w) => $cursor->greaterThanOrEqualTo($w[0]) && $cursor->lessThan($w[1]));
                $key = implode('|', [$dayType, $holidayType ?? '', $isOvertime ? 1 : 0, $isNight ? 1 : 0]);
                $buckets[$key] = ($buckets[$key] ?? 0) + $minutes;
                $used += $minutes;
                $cursor = $cursor->addMinutes($minutes);
            }
        }

        return $buckets;
    }

    /** @return array{0: string, 1: string} status, absence days */
    private function status(string $dayType, array $events, array $exceptions, int $late, float $leaveDays, bool $excused): array
    {
        if ($excused) {
            return ['excused', '0'];
        }
        if (! $events) {
            return match (true) {
                $leaveDays >= 1 => ['on_leave', '0'],
                $dayType === 'holiday' => ['holiday', '0'],
                $dayType === 'rest_day' => ['rest_day', '0'],
                $dayType === 'no_schedule' => ['no_schedule', '0'],
                default => [$leaveDays > 0 ? 'on_leave' : 'absent', bcsub('1', (string) $leaveDays, 4)],
            };
        }
        if ($exceptions) {
            return ['incomplete', '0'];
        }

        return [$late > 0 ? 'late' : 'present', '0'];
    }
}
