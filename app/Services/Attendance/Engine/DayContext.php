<?php

namespace App\Services\Attendance\Engine;

use App\Models\AttendancePolicy;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use Carbon\CarbonImmutable;

/**
 * Resolved inputs of one employee work date. Scheduled times are real
 * instants in the employee's timezone (overnight shifts end the next day).
 */
final class DayContext
{
    public function __construct(
        public readonly Employee $employee,
        public readonly string $date,
        public readonly string $timezone,
        public readonly AttendancePolicy $policy,
        public readonly ?Shift $shift,
        public readonly bool $isRestDay,
        public readonly bool $hasSchedule,
        public readonly ?Holiday $holiday,
        public readonly string $leaveDays,
    ) {}

    public function dayType(): string
    {
        return match (true) {
            $this->holiday !== null => 'holiday',
            $this->isRestDay => 'rest_day',
            $this->shift !== null => 'workday',
            default => 'no_schedule',
        };
    }

    public function scheduledStart(): ?CarbonImmutable
    {
        return $this->shift?->start_time ? CarbonImmutable::parse("{$this->date} {$this->shift->start_time}", $this->timezone) : null;
    }

    public function scheduledEnd(): ?CarbonImmutable
    {
        $start = $this->scheduledStart();
        if (! $start || ! $this->shift->end_time) {
            return null;
        }

        $end = CarbonImmutable::parse("{$this->date} {$this->shift->end_time}", $this->timezone);

        return ($this->shift->is_overnight || $end->lessThanOrEqualTo($start)) ? $end->addDay() : $end;
    }

    /** Minutes the shift requires (its required hours, else the span start→end). */
    public function requiredMinutes(): int
    {
        if (! $this->shift) {
            return 0;
        }
        if ((float) $this->shift->required_hours > 0) {
            return (int) round((float) $this->shift->required_hours * 60);
        }

        return ($this->scheduledStart() && $this->scheduledEnd()) ? (int) $this->scheduledStart()->diffInMinutes($this->scheduledEnd()) : 0;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable}|null scheduled unpaid break */
    public function scheduledBreak(): ?array
    {
        $start = $this->scheduledStart();
        if (! $start || ! $this->shift->break_start || ! $this->shift->break_end) {
            return null;
        }

        $breakStart = CarbonImmutable::parse("{$this->date} {$this->shift->break_start}", $this->timezone);
        if ($breakStart->lessThan($start)) {
            $breakStart = $breakStart->addDay();
        }
        $breakEnd = CarbonImmutable::parse($breakStart->toDateString().' '.$this->shift->break_end, $this->timezone);
        if ($breakEnd->lessThanOrEqualTo($breakStart)) {
            $breakEnd = $breakEnd->addDay();
        }

        return [$breakStart, $breakEnd];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} instants whose events belong to this work date */
    public function window(): array
    {
        $start = $this->scheduledStart();
        $from = $start
            ? $start->subMinutes($this->policy->window_before_minutes ?? 240)
            : CarbonImmutable::parse("{$this->date} ".($this->policy->day_start_time ?? '00:00:00'), $this->timezone);

        return [$from->utc(), $from->addDay()->utc()];
    }

    /** @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}> night windows touching this work date */
    public function nightWindows(): array
    {
        $startTime = $this->policy->night_start;
        $endTime = $this->policy->night_end;
        if (! $startTime || ! $endTime) {
            return [];
        }

        $windows = [];
        foreach ([-1, 0, 1] as $offset) {
            $day = CarbonImmutable::parse($this->date, $this->timezone)->addDays($offset)->toDateString();
            $from = CarbonImmutable::parse("{$day} {$startTime}", $this->timezone);
            $to = CarbonImmutable::parse("{$day} {$endTime}", $this->timezone);
            if ($to->lessThanOrEqualTo($from)) {
                $to = $to->addDay();
            }
            $windows[] = [$from->utc(), $to->utc()];
        }

        return $windows;
    }
}
