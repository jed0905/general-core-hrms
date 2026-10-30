<?php

namespace App\Services\Payroll;

use App\Models\PayGroup;
use App\Models\PayGroupCalendar;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generates a pay group's periods for a year from its frequency:
 * monthly (calendar months), semi_monthly (1st-15th, 16th-end), weekly and
 * biweekly (consecutive 7/14-day periods from an anchor start date, for every
 * period that starts in the year). Pay date = period end + offset days.
 * tax_year is the pay date's year; a localization with another tax year
 * re-derives it from the pay date. Periods never overlap within a pay group.
 */
class PayrollCalendarGenerator
{
    public function __construct(protected PayrollAuditLogger $audit) {}

    public function generate(PayGroup $payGroup, int $year, array $options, User $user): PayGroupCalendar
    {
        return DB::transaction(function () use ($payGroup, $year, $options, $user) {
            PayGroup::whereKey($payGroup->id)->lockForUpdate()->first();

            if (PayGroupCalendar::where('pay_group_id', $payGroup->id)->where('calendar_year', $year)->exists()) {
                throw ValidationException::withMessages(['calendar_year' => "A {$year} calendar already exists for this pay group."]);
            }

            $offset = (int) ($options['pay_date_offset_days'] ?? 0);
            $ranges = $this->ranges($payGroup->pay_frequency, $year, $options['first_period_start'] ?? null);

            [$first, $last] = [$ranges[0][0], end($ranges)[1]];
            if (PayrollPeriod::where('pay_group_id', $payGroup->id)
                ->where('period_start', '<=', $last->toDateString())
                ->where('period_end', '>=', $first->toDateString())
                ->exists()) {
                throw ValidationException::withMessages(['first_period_start' => 'These periods would overlap existing periods of this pay group.']);
            }

            $calendar = PayGroupCalendar::create([
                'pay_group_id' => $payGroup->id,
                'calendar_year' => $year,
                'name' => "{$payGroup->name} {$year}",
                'created_by' => $user->id,
            ]);

            foreach ($ranges as $index => [$start, $end]) {
                $payDate = $end->addDays($offset);
                PayrollPeriod::create([
                    'payroll_calendar_id' => $calendar->id,
                    'pay_group_id' => $payGroup->id,
                    'period_number' => $index + 1,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'cutoff_date' => $end->toDateString(),
                    'pay_date' => $payDate->toDateString(),
                    'tax_year' => $payDate->year,
                    'status' => PayrollPeriod::STATUS_OPEN,
                ]);
            }

            $this->audit->record($calendar, 'created', $user, ['periods' => count($ranges), 'pay_date_offset_days' => $offset]);

            return $calendar->load('periods');
        });
    }

    public function updatePeriod(PayrollPeriod $period, array $data, User $user): PayrollPeriod
    {
        if ($period->runs()->where('status', '<>', PayrollRun::STATUS_CANCELLED)->exists()) {
            throw ValidationException::withMessages(['pay_date' => 'Dates of a period with payroll runs cannot change.']);
        }

        $period->fill(array_intersect_key($data, array_flip(['cutoff_date', 'pay_date'])));
        $period->tax_year = Carbon::parse($period->pay_date)->year;
        $changes = $this->audit->pendingChanges($period);
        $period->save();
        $this->audit->record($period, 'updated', $user, $changes);

        return $period;
    }

    /** Close (no new regular runs) or reopen a period. */
    public function setStatus(PayrollPeriod $period, string $status, User $user): PayrollPeriod
    {
        if ($status === PayrollPeriod::STATUS_CLOSED && $period->runs()->whereNotIn('status', PayrollRun::TERMINAL)->exists()) {
            throw ValidationException::withMessages(['status' => 'Finalize or cancel this period\'s open runs first.']);
        }

        $period->update(['status' => $status]);
        $this->audit->record($period, $status, $user, ['status' => $status]);

        return $period;
    }

    /** @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}> */
    protected function ranges(string $frequency, int $year, ?string $anchor): array
    {
        $ranges = [];

        if ($frequency === PayGroup::FREQUENCY_MONTHLY || $frequency === PayGroup::FREQUENCY_SEMI_MONTHLY) {
            for ($month = 1; $month <= 12; $month++) {
                $start = CarbonImmutable::create($year, $month, 1);
                if ($frequency === PayGroup::FREQUENCY_MONTHLY) {
                    $ranges[] = [$start, $start->endOfMonth()->startOfDay()];
                } else {
                    $ranges[] = [$start, $start->setDay(15)];
                    $ranges[] = [$start->setDay(16), $start->endOfMonth()->startOfDay()];
                }
            }

            return $ranges;
        }

        if (! $anchor) {
            throw ValidationException::withMessages(['first_period_start' => 'Weekly and bi-weekly calendars need the first period\'s start date.']);
        }

        $length = $frequency === PayGroup::FREQUENCY_WEEKLY ? 7 : 14;
        $start = CarbonImmutable::parse($anchor);
        if ($start->year !== $year) {
            throw ValidationException::withMessages(['first_period_start' => "The first period must start in {$year}."]);
        }

        while ($start->year === $year) {
            $ranges[] = [$start, $start->addDays($length - 1)];
            $start = $start->addDays($length);
        }

        return $ranges;
    }
}
