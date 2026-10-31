<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDay;
use App\Models\AttendancePeriodSummary;
use App\Models\EmploymentAssignment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\User;
use App\Services\Payroll\Contracts\MapsAttendanceToPremiums;
use App\Services\Payroll\Localization\LocalizationRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns attendance days into payroll-period quantities (the only way payroll
 * reads attendance). Core quantities: days_worked, hours_worked (regular,
 * non-overtime), absence_days, late_hours, undertime_hours. Premium hours come
 * from the minute buckets, mapped to categories by the payroll localization
 * (MapsAttendanceToPremiums); attendance holds no country rule.
 *
 * Summaries are regenerated freely while draft (or approved but unused);
 * once a finalized run consumed one, it never changes.
 */
class AttendancePeriodService
{
    public function __construct(
        protected LocalizationRegistry $localizations,
        protected AttendanceAuditLogger $audit,
    ) {}

    /** @return Collection<int, AttendancePeriodSummary> */
    public function generate(PayrollPeriod $period, User $user): Collection
    {
        $period->loadMissing('payGroup');
        $from = $period->period_start->toDateString();
        $to = $period->period_end->toDateString();
        $localization = $this->localizations->for($period->payGroup->payroll_country_code);
        $mapper = $localization instanceof MapsAttendanceToPremiums ? $localization : null;

        $assignments = EmploymentAssignment::where('pay_group_id', $period->pay_group_id)->overlapping($from, $to)->get();

        return DB::transaction(fn () => $assignments->map(function (EmploymentAssignment $assignment) use ($period, $from, $to, $mapper, $user) {
            $start = max($from, $assignment->effective_from->toDateString());
            $end = $assignment->effective_to ? min($to, $assignment->effective_to->toDateString()) : $to;
            $days = AttendanceDay::where('employee_id', $assignment->employee_id)->whereBetween('work_date', [$start, $end])->get();
            [$quantities, $details] = $this->quantities($days, $mapper);

            $summary = AttendancePeriodSummary::firstOrNew(['employee_id' => $assignment->employee_id, 'payroll_period_id' => $period->id]);
            if ($summary->consumed_run_id) {
                return $summary; // used by finalized payroll: never rewritten
            }

            $changed = $summary->quantities !== $quantities;
            $summary->fill([
                'quantities' => $quantities,
                'details' => $details + ['days' => $days->count(), 'from' => $start, 'to' => $end],
                'computed_at' => now(),
                'status' => $summary->exists && ! $changed ? $summary->status : AttendancePeriodSummary::STATUS_DRAFT,
                'approved_by' => $summary->exists && ! $changed ? $summary->approved_by : null,
                'approved_at' => $summary->exists && ! $changed ? $summary->approved_at : null,
            ])->save();
            $this->audit->record($summary, 'generated', $user, ['changed' => $changed]);

            return $summary;
        }));
    }

    /** @param  array<int, int>  $ids */
    public function approve(array $ids, User $user): int
    {
        return DB::transaction(function () use ($ids, $user) {
            $summaries = AttendancePeriodSummary::whereIn('id', $ids)->where('status', AttendancePeriodSummary::STATUS_DRAFT)->lockForUpdate()->get();
            foreach ($summaries as $summary) {
                if (! empty($summary->details['open_exceptions'])) {
                    throw ValidationException::withMessages(['ids' => "Resolve the attendance exceptions of employee #{$summary->employee_id} first."]);
                }
                $summary->update(['status' => AttendancePeriodSummary::STATUS_APPROVED, 'approved_by' => $user->id, 'approved_at' => now()]);
                $this->audit->record($summary, 'approved', $user);
            }

            return $summaries->count();
        });
    }

    /** Payroll finalized a run: lock the summaries it used (listener of PayrollRunFinalized). */
    public function markConsumed(PayrollRun $run): void
    {
        if ($run->run_type !== PayrollRun::TYPE_REGULAR || $run->payGroup->time_source !== 'attendance') {
            return;
        }

        $employeeIds = PayrollRunEmployee::where('payroll_run_id', $run->id)->where('status', PayrollRunEmployee::STATUS_INCLUDED)->pluck('employee_id');
        AttendancePeriodSummary::where('payroll_period_id', $run->payroll_period_id)
            ->whereIn('employee_id', $employeeIds)
            ->where('status', AttendancePeriodSummary::STATUS_APPROVED)
            ->whereNull('consumed_run_id')
            ->get()
            ->each(fn (AttendancePeriodSummary $s) => $s->update(['consumed_run_id' => $run->id]));
    }

    /** @return array{0: array<string, string>, 1: array<string, mixed>} */
    protected function quantities(Collection $days, ?MapsAttendanceToPremiums $mapper): array
    {
        $hours = fn (int|float $minutes) => bcdiv((string) $minutes, '60', 4);
        $quantities = [
            'days_worked' => (string) $days->where('worked_minutes', '>', 0)->count(),
            'hours_worked' => $hours($days->sum('regular_minutes')),
            'absence_days' => bcadd((string) $days->sum(fn ($d) => (float) $d->absence_days), '0', 4),
            'late_hours' => $hours($days->sum('late_minutes')),
            'undertime_hours' => $hours($days->sum('undertime_minutes')),
        ];

        $premiumMinutes = [];
        $unmapped = [];
        foreach ($days as $day) {
            foreach ($day->buckets ?? [] as $key => $minutes) {
                [$dayType, $holidayType, $overtime, $night] = explode('|', $key) + [null, null, '0', '0'];
                $category = $mapper?->attendancePremiumCategory($dayType, $holidayType ?: null, $overtime === '1', $night === '1');
                if ($category) {
                    $premiumMinutes[$category] = ($premiumMinutes[$category] ?? 0) + $minutes;
                } elseif ($overtime === '1' || $dayType !== 'workday') {
                    $unmapped[$key] = ($unmapped[$key] ?? 0) + $minutes;
                }
            }
        }
        ksort($premiumMinutes);
        foreach ($premiumMinutes as $category => $minutes) {
            $quantities[$category] = $hours($minutes);
        }

        $quantities = array_filter($quantities, fn ($v) => bccomp($v, '0', 4) !== 0);
        $openExceptions = $days->filter(fn ($d) => $d->status === 'incomplete')->map(fn ($d) => $d->work_date->toDateString())->values()->all();

        return [$quantities, ['unmapped_minutes' => $unmapped, 'open_exceptions' => $openExceptions]];
    }
}
