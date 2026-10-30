<?php

namespace App\Services\Payroll\Calculation;

use App\Models\EmployeeCompensation;
use App\Models\EmployeePayComponent;
use App\Models\EmployeeStatutoryProfile;
use App\Models\EmployeeTaxResidency;
use App\Models\EmploymentAssignment;
use App\Models\PayComponent;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\PayrollRunException;
use App\Models\PayrollRunLine;
use App\Models\PayrollTimeInput;
use App\Services\Payroll\Contracts\LeaveInputProvider;
use App\Services\Payroll\Contracts\PayrollLocalization;
use App\Services\Payroll\Contracts\TimeInputProvider;
use App\Services\Payroll\Inputs\WorkingDayCounter;
use App\Services\Payroll\Localization\LocalizationRegistry;
use App\Services\Payroll\Rules\MissingStatutoryRuleException;
use App\Services\Payroll\Rules\StatutoryRuleResolver;
use App\Services\Payroll\Support\CurrencyPrecision;
use App\Services\Payroll\Support\Decimal;
use App\Services\Payroll\Support\ExchangeRateConverter;
use App\Services\Payroll\Support\RateConverter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Global Payroll Core: calculates one assignment's payslip for one run.
 * Country-neutral: every statutory decision is delegated to the localization
 * registered for the assignment's payroll country. Pipeline:
 *
 *   1 context  2 base pay  3 attendance & leave  4 premium time
 *   5 recurring components & adjustments  6 classify  7 statutory lines
 *   8 tax lines  9 round, total, check invariants  10 snapshot
 *
 * Supplemental runs pay approved adjustments only (steps 2-5 limited to adjustments).
 * Reversal runs are not calculated here (PayrollRunService negates the original).
 */
class PayrollCalculator
{
    /** @var array<string, int>|null engine (system) component ids by code, loaded once */
    private ?array $systemComponents = null;

    public function __construct(
        protected LocalizationRegistry $localizations,
        protected TimeInputProvider $timeInputs,
        protected LeaveInputProvider $leaveInputs,
        protected CurrencyPrecision $precision,
        protected WorkingDayCounter $workingDays,
    ) {}

    public function calculate(PayrollRun $run, EmploymentAssignment $assignment, StatutoryRuleResolver $rules): CalculationResult
    {
        $period = $run->period;
        $payGroup = $run->payGroup;
        $employee = $assignment->employee;
        $periodEnd = $period->period_end->toDateString();
        $exchange = new ExchangeRateConverter;
        $converter = new RateConverter((string) $payGroup->hours_per_day, (string) $payGroup->annual_working_days);

        $context = new PayrollContext(
            run: $run,
            period: $period,
            payGroup: $payGroup,
            legalEntity: $run->legalEntity,
            assignment: $assignment,
            employee: $employee,
            currency: $run->currency,
            minorUnits: $this->precision->minorUnits($run->currency),
            converter: $converter,
            rules: $rules,
            residency: $this->residency($assignment, $periodEnd),
            statutoryProfile: $this->statutoryProfile($assignment, $periodEnd),
            timeInputs: $run->run_type === PayrollRun::TYPE_REGULAR ? $this->timeInputs->quantities($employee, $period) : [],
            leave: $run->run_type === PayrollRun::TYPE_REGULAR
                ? $this->leaveInputs->summary($employee, $this->coverageStart($assignment, $period), $this->coverageEnd($assignment, $period), (string) $payGroup->hours_per_day)
                : ['paid_days' => '0', 'unpaid_days' => '0', 'dates' => []],
        );

        $localization = $this->localizations->for($context->countryCode());
        if (! $localization) {
            $context->error('NO_LOCALIZATION', "No payroll localization is registered for country {$context->countryCode()}; statutory deductions and tax can't be calculated.");
        }

        $lines = [];
        $compensations = collect();

        try {
            if ($run->run_type === PayrollRun::TYPE_REGULAR) {
                $compensations = $this->compensations($assignment, $period->period_start->toDateString(), $periodEnd);
                $lines = [
                    ...$this->basePay($context, $compensations, $exchange),
                    ...$this->attendance($context),
                    ...$this->premiumTime($context, $localization),
                    ...$this->recurringComponents($context, $exchange),
                ];
            }

            $lines = [...$lines, ...$this->adjustments($context, $exchange)];
            $this->applyConfiguredTreatments($context, $lines);
            // Statutory bases are built from the amounts the payslip will show.
            $lines = $this->round($context, $lines);

            $treatment = new ComponentTreatment;
            if ($localization && ! $context->hasErrors()) {
                $treatment = $localization->classify($context, $lines);
                $statutory = $localization->statutoryLines($context, $treatment);
                $tax = $localization->taxLines($context, $treatment, $statutory);
                $lines = [...$lines, ...$statutory, ...$tax];
            }
        } catch (MissingStatutoryRuleException|RuntimeException $e) {
            $context->error($e instanceof MissingStatutoryRuleException ? 'STATUTORY_RULE_MISSING' : 'CALCULATION_FAILED', $e->getMessage());
            $treatment = new ComponentTreatment;
        }

        $lines = $this->round($context, $lines);
        $totals = $this->totals($context, $lines, $treatment);

        if (Decimal::isNegative($totals['net_pay'])) {
            $context->error('NEGATIVE_NET_PAY', 'Net pay is negative ('.$totals['net_pay'].' '.$context->currency.'). Reduce deductions or move them to a later period.');
        }

        return new CalculationResult(
            lines: $lines,
            totals: $totals,
            snapshot: $this->snapshot($context, $compensations, $exchange, $localization),
            exceptions: $context->exceptions,
            ruleVersions: $rules->used(),
        );
    }

    /* ---------------------------------------------------------------- inputs */

    protected function residency(EmploymentAssignment $assignment, string $date): ?EmployeeTaxResidency
    {
        return EmployeeTaxResidency::where('employee_id', $assignment->employee_id)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->first();
    }

    /** @return array<string, mixed> */
    protected function statutoryProfile(EmploymentAssignment $assignment, string $date): array
    {
        return EmployeeStatutoryProfile::where('employee_id', $assignment->employee_id)
            ->where('country_code', $assignment->payroll_country_code)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->first()?->fields ?? [];
    }

    /** Approved compensation records overlapping the period, oldest first. */
    protected function compensations(EmploymentAssignment $assignment, string $from, string $to): Collection
    {
        return EmployeeCompensation::where('employment_assignment_id', $assignment->id)
            ->where('status', EmployeeCompensation::STATUS_APPROVED)
            ->where('effective_from', '<=', $to)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
            ->orderBy('effective_from')
            ->get();
    }

    protected function coverageStart(EmploymentAssignment $assignment, $period): string
    {
        return max($period->period_start->toDateString(), $assignment->effective_from->toDateString());
    }

    protected function coverageEnd(EmploymentAssignment $assignment, $period): string
    {
        $end = $period->period_end->toDateString();

        return $assignment->effective_to ? min($end, $assignment->effective_to->toDateString()) : $end;
    }

    /* -------------------------------------------------------------- base pay */

    /**
     * Salaried bases: period amount x share of the period each compensation
     * record covers (calendar or scheduled working days). Daily/hourly bases:
     * approved days/hours worked plus approved paid leave, at the record in
     * force at the end of the covered period.
     *
     * @return array<int, PayLine>
     */
    protected function basePay(PayrollContext $context, Collection $compensations, ExchangeRateConverter $exchange): array
    {
        if ($compensations->isEmpty()) {
            $context->error('NO_COMPENSATION', 'No approved compensation is in effect for this assignment during the period.');

            return [];
        }

        $period = $context->period;
        $coverageStart = $this->coverageStart($context->assignment, $period);
        $coverageEnd = $this->coverageEnd($context->assignment, $period);
        $rateDate = $period->period_end->toDateString();
        $basic = $this->componentId('BASIC');

        $converted = $compensations->map(fn (EmployeeCompensation $c) => [
            'record' => $c,
            'amount' => $exchange->convert((string) $c->amount, $c->currency, $context->currency, $rateDate),
        ]);

        $reference = $converted->last();
        $context->referenceAmount = $reference['amount'];
        $context->referenceBasis = $reference['record']->basis;

        if ($reference['record']->isTimeBased()) {
            return $this->timeBasedPay($context, $reference, $converted->count(), $basic);
        }

        $periodUnits = $this->units($context, $period->period_start->toDateString(), $period->period_end->toDateString());
        if ($periodUnits === 0) {
            $context->error('NO_WORKING_DAYS', 'The period has no scheduled working days, so salary can\'t be prorated by working days.');

            return [];
        }

        $lines = [];
        foreach ($converted as $item) {
            /** @var EmployeeCompensation $record */
            $record = $item['record'];
            if ($record->isTimeBased()) {
                $context->error('MIXED_COMPENSATION_BASIS', 'Salaried and daily/hourly compensation records overlap in one period; split them with an assignment change.');

                continue;
            }

            $from = max($coverageStart, $record->effective_from->toDateString());
            $to = $record->effective_to ? min($coverageEnd, $record->effective_to->toDateString()) : $coverageEnd;
            if ($from > $to) {
                continue;
            }

            $periodAmount = $context->converter->periodAmount($item['amount'], $record->basis, $context->payGroup->pay_frequency);
            $units = $this->units($context, $from, $to);
            $share = $units === $periodUnits ? '1' : Decimal::div((string) $units, (string) $periodUnits);

            $lines[] = new PayLine(
                lineClass: PayrollRunLine::CLASS_EARNING,
                code: 'BASIC',
                description: 'Basic pay',
                amount: Decimal::mul($periodAmount, $share),
                quantity: Decimal::format($share, 4),
                rate: Decimal::format($periodAmount, 8),
                payComponentId: $basic,
                inputs: [
                    'compensation_id' => $record->id, 'basis' => $record->basis, 'from' => $from, 'to' => $to,
                    'units' => $units, 'period_units' => $periodUnits, 'proration' => $context->payGroup->proration_method,
                ],
            );
        }

        return $lines;
    }

    /** @return array<int, PayLine> */
    protected function timeBasedPay(PayrollContext $context, array $reference, int $records, ?int $basic): array
    {
        if ($records > 1) {
            $context->warning('RATE_CHANGE_IN_PERIOD', 'The daily/hourly rate changed during the period; the rate in force at period end was used for all time worked.');
        }

        $record = $reference['record'];
        $daily = $record->basis === 'daily';
        $quantity = $context->timeInputs[$daily ? PayrollTimeInput::DAYS_WORKED : PayrollTimeInput::HOURS_WORKED] ?? null;
        $lines = [];

        if ($quantity === null) {
            $context->warning('NO_TIME_WORKED', 'No approved '.($daily ? 'days' : 'hours').' worked for a '.$record->basis.'-rated employee; basic pay is zero.');
        } else {
            $lines[] = new PayLine(
                lineClass: PayrollRunLine::CLASS_EARNING,
                code: 'BASIC',
                description: 'Basic pay',
                amount: Decimal::mul($quantity, $reference['amount']),
                quantity: Decimal::format($quantity, 4),
                rate: Decimal::format($reference['amount'], 8),
                payComponentId: $basic,
                inputs: ['compensation_id' => $record->id, 'basis' => $record->basis, 'unit' => $daily ? 'days' : 'hours'],
            );
        }

        $paidLeaveDays = $context->leave['paid_days'];
        if (! Decimal::isZero($paidLeaveDays)) {
            $rate = $context->dailyRate();
            $lines[] = new PayLine(
                lineClass: PayrollRunLine::CLASS_EARNING,
                code: 'PAID_LEAVE',
                description: 'Paid leave',
                amount: Decimal::mul($paidLeaveDays, $rate),
                quantity: Decimal::format($paidLeaveDays, 4),
                rate: Decimal::format($rate, 8),
                payComponentId: $this->componentId('PAID_LEAVE'),
                inputs: ['leave_dates' => collect($context->leave['dates'])->where('paid', true)->values()->all()],
            );
        }

        return $lines;
    }

    protected function units(PayrollContext $context, string $from, string $to): int
    {
        return $context->payGroup->proration_method === 'working_days'
            ? $this->workingDays->count($context->employee, $from, $to)
            : (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
    }

    /* ----------------------------------------------------- attendance & leave */

    /**
     * Salaried employees only: approved absences, leave without pay, late and
     * undertime reduce pay at the derived daily/hourly rate. (Daily/hourly-rated
     * employees are simply paid for time worked.)
     *
     * @return array<int, PayLine>
     */
    protected function attendance(PayrollContext $context): array
    {
        if ($context->referenceAmount === null || in_array($context->referenceBasis, EmployeeCompensation::TIME_BASED, true)) {
            return [];
        }

        $daily = $context->dailyRate();
        $hourly = $context->hourlyRate();
        $items = [
            ['ABSENCE', 'Absences', $context->timeInputs[PayrollTimeInput::ABSENCE_DAYS] ?? '0', $daily, 'days'],
            ['LWOP', 'Leave without pay', $context->leave['unpaid_days'], $daily, 'days'],
            ['LATE', 'Tardiness', $context->timeInputs[PayrollTimeInput::LATE_HOURS] ?? '0', $hourly, 'hours'],
            ['UNDERTIME', 'Undertime', $context->timeInputs[PayrollTimeInput::UNDERTIME_HOURS] ?? '0', $hourly, 'hours'],
        ];

        $lines = [];

        foreach ($items as [$code, $label, $quantity, $rate, $unit]) {
            if (Decimal::isZero($quantity)) {
                continue;
            }

            $inputs = ['unit' => $unit];
            if ($code === 'LWOP') {
                $inputs['leave_dates'] = collect($context->leave['dates'])->where('paid', false)->values()->all();
            }

            $lines[] = new PayLine(
                lineClass: PayrollRunLine::CLASS_EARNING,
                code: $code,
                description: $label,
                amount: Decimal::neg(Decimal::mul($quantity, $rate)),
                quantity: Decimal::format($quantity, 4),
                rate: Decimal::format($rate, 8),
                payComponentId: $this->componentId($code),
                inputs: $inputs,
            );
        }

        return $lines;
    }

    /* ---------------------------------------------------------- premium time */

    /**
     * Premium categories (overtime, holiday, rest day, night work...) are
     * declared by the localization, and their multipliers come from its rules.
     * No rule means no pay and an error; there is no default multiplier.
     *
     * @return array<int, PayLine>
     */
    protected function premiumTime(PayrollContext $context, ?PayrollLocalization $localization): array
    {
        $premiumInputs = array_diff_key($context->timeInputs, PayrollTimeInput::CORE_CATEGORIES);
        $premiumInputs = array_filter($premiumInputs, fn ($quantity) => ! Decimal::isZero($quantity));

        if ($premiumInputs === [] || ! $localization || $context->referenceAmount === null) {
            return [];
        }

        $categories = $localization->premiumCategories();
        $rules = $localization->premiumRules($context);
        $hourly = $context->hourlyRate();
        $componentId = $this->componentId('PREMIUM');
        $lines = [];

        foreach ($premiumInputs as $category => $hours) {
            if (! isset($categories[$category])) {
                $context->error('UNKNOWN_TIME_CATEGORY', "Time input category {$category} is not defined for {$context->countryCode()}.");

                continue;
            }

            $rule = $rules->for($category);
            if (! $rule) {
                $context->error('PREMIUM_RULE_MISSING', "No premium rule is in effect for {$categories[$category]}; {$hours} hours were not paid.");

                continue;
            }

            $lines[] = new PayLine(
                lineClass: PayrollRunLine::CLASS_EARNING,
                code: 'PREMIUM_'.strtoupper($category),
                description: $rule->label,
                amount: Decimal::mul(Decimal::mul($hourly, $hours), $rule->multiplier),
                quantity: Decimal::format($hours, 4),
                rate: Decimal::format($hourly, 8),
                multiplier: Decimal::format($rule->multiplier, 8),
                payComponentId: $componentId,
                ruleVersionId: $rule->ruleVersionId,
                inputs: ['category' => $category, 'unit' => 'hours'],
            );
        }

        return $lines;
    }

    /* -------------------------------------------- components & adjustments */

    /**
     * Recurring allowances and deductions active on the last day of the period.
     * A total_limit (e.g. a loan) stops the component once finalized lines reach it.
     *
     * @return array<int, PayLine>
     */
    protected function recurringComponents(PayrollContext $context, ExchangeRateConverter $exchange): array
    {
        $date = $this->coverageEnd($context->assignment, $context->period);
        $components = EmployeePayComponent::with('payComponent')
            ->where('employment_assignment_id', $context->assignment->id)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->get()
            ->filter(fn (EmployeePayComponent $c) => $c->payComponent?->is_active)
            ->sortBy(fn (EmployeePayComponent $c) => [$c->payComponent->priority, $c->id]);

        $lines = [];

        foreach ($components as $component) {
            $amount = $exchange->convert((string) $component->amount, $component->currency, $context->currency, $context->period->period_end->toDateString());
            if ($component->amount_basis === 'monthly') {
                $amount = Decimal::div(Decimal::mul($amount, '12'), (string) $context->payGroup->periodsPerYear());
            }

            $inputs = ['employee_pay_component_id' => $component->id, 'amount_basis' => $component->amount_basis];

            if ($component->total_limit !== null) {
                $paid = Decimal::sum(PayrollRunLine::query()
                    ->where('inputs->employee_pay_component_id', $component->id)
                    ->whereHas('run', fn ($q) => $q->where('status', PayrollRun::STATUS_FINALIZED))
                    ->pluck('amount')
                    ->map(fn ($a) => (string) $a));
                $remaining = Decimal::sub((string) $component->total_limit, $paid);

                if (Decimal::cmp($remaining, '0') <= 0) {
                    continue;
                }

                $amount = Decimal::min($amount, $remaining);
                $inputs += ['total_limit' => (string) $component->total_limit, 'paid_before' => Decimal::format($paid, 4)];
            }

            $lines[] = $this->componentLine($component->payComponent, $amount, $inputs);
        }

        return $lines;
    }

    /** @return array<int, PayLine> */
    protected function adjustments(PayrollContext $context, ExchangeRateConverter $exchange): array
    {
        return PayrollAdjustment::with('payComponent')
            ->where('payroll_period_id', $context->period->id)
            ->where('employee_id', $context->employee->id)
            ->where('status', PayrollAdjustment::STATUS_APPROVED)
            ->orderBy('id')
            ->get()
            ->map(function (PayrollAdjustment $adjustment) use ($context, $exchange) {
                $amount = $exchange->convert((string) $adjustment->amount, $adjustment->currency, $context->currency, $context->period->period_end->toDateString());
                $line = $this->componentLine($adjustment->payComponent, $amount, [
                    'adjustment_id' => $adjustment->id,
                    'corrects_run_employee_id' => $adjustment->corrects_run_employee_id,
                    'remarks' => $adjustment->remarks,
                ]);
                $line->adjustmentId = $adjustment->id;

                return $line;
            })
            ->all();
    }

    protected function componentLine(PayComponent $component, string $amount, array $inputs): PayLine
    {
        return new PayLine(
            lineClass: $component->line_class,
            code: $component->code,
            description: $component->name,
            amount: $amount,
            payComponentId: $component->id,
            inputs: $inputs,
        );
    }

    /**
     * Copy each component's configured treatment for this country onto its line.
     * The localization interprets (and may require) them in classify().
     *
     * @param  array<int, PayLine>  $lines
     */
    protected function applyConfiguredTreatments(PayrollContext $context, array $lines): void
    {
        $ids = array_filter(array_map(fn (PayLine $l) => $l->payComponentId, $lines));
        $treatments = PayComponent::with(['treatments' => fn ($q) => $q->where('country_code', $context->countryCode())])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($lines as $line) {
            $treatment = $line->payComponentId ? $treatments->get($line->payComponentId)?->treatments->first() : null;
            $line->taxTreatment ??= $treatment?->tax_treatment;
            $line->contributionTreatment ??= $treatment?->contribution_treatment;
        }
    }

    /* ------------------------------------------------------ rounding & totals */

    /**
     * Round each line once: the rule's places/mode when it set them, otherwise the
     * currency's minor units and the pay group's rounding mode.
     *
     * @param  array<int, PayLine>  $lines
     * @return array<int, PayLine>
     */
    protected function round(PayrollContext $context, array $lines): array
    {
        foreach ($lines as $line) {
            $places = min($line->roundingPlaces ?? $context->minorUnits, $context->minorUnits);
            $line->amount = Decimal::format(
                Decimal::round($line->amount, $places, $line->roundingMode ?? $context->payGroup->rounding_mode),
                4
            );
        }

        return array_values(array_filter($lines, fn (PayLine $l) => ! Decimal::isZero($l->amount) || $l->lineClass === PayrollRunLine::CLASS_INFORMATIONAL));
    }

    /**
     * gross = sum of earnings; net = gross - employee deductions - employee tax;
     * employer cost = gross + employer contributions. All over rounded lines.
     *
     * @param  array<int, PayLine>  $lines
     * @return array<string, string>
     */
    protected function totals(PayrollContext $context, array $lines, ComponentTreatment $treatment): array
    {
        $sum = fn (string $class) => Decimal::sum(array_map(fn (PayLine $l) => $l->amount, array_filter($lines, fn (PayLine $l) => $l->lineClass === $class)));

        $gross = $sum(PayrollRunLine::CLASS_EARNING);
        $deductions = $sum(PayrollRunLine::CLASS_EMPLOYEE_DEDUCTION);
        $tax = $sum(PayrollRunLine::CLASS_EMPLOYEE_TAX);
        $employer = $sum(PayrollRunLine::CLASS_EMPLOYER_CONTRIBUTION);

        return array_map(fn ($v) => Decimal::format($v, 4), [
            'gross_pay' => $gross,
            'employee_deductions' => $deductions,
            'tax' => $tax,
            'net_pay' => Decimal::sub(Decimal::sub($gross, $deductions), $tax),
            'employer_contributions' => $employer,
            'employer_cost' => Decimal::add($gross, $employer),
            'taxable_income' => Decimal::round($treatment->taxableIncome, $context->minorUnits, $context->payGroup->rounding_mode),
        ]);
    }

    /** @return array<string, mixed> */
    protected function snapshot(PayrollContext $context, Collection $compensations, ExchangeRateConverter $exchange, ?PayrollLocalization $localization): array
    {
        $sensitive = collect($localization?->statutoryProfileFields() ?? [])->filter(fn ($f) => $f['sensitive'] ?? false)->keys()->all();

        return [
            'engine_version' => config('payroll.engine_version'),
            'localization' => $localization?->countryCode(),
            'currency' => $context->currency,
            'minor_units' => $context->minorUnits,
            'rounding_mode' => $context->payGroup->rounding_mode,
            'assignment' => $context->assignment->only(['id', 'legal_entity_id', 'pay_group_id', 'location_id', 'work_country_code', 'work_subdivision_code', 'payroll_country_code']),
            'pay_group' => [
                'pay_frequency' => $context->payGroup->pay_frequency,
                'hours_per_day' => (string) $context->payGroup->hours_per_day,
                'annual_working_days' => (string) $context->payGroup->annual_working_days,
                'proration_method' => $context->payGroup->proration_method,
            ],
            'period' => [
                'id' => $context->period->id,
                'start' => $context->period->period_start->toDateString(),
                'end' => $context->period->period_end->toDateString(),
                'pay_date' => $context->period->pay_date->toDateString(),
                'tax_year' => $context->period->tax_year,
            ],
            'compensation_ids' => $compensations->pluck('id')->all(),
            'compensations' => $compensations->map(fn (EmployeeCompensation $c) => [
                'id' => $c->id, 'basis' => $c->basis, 'amount' => (string) $c->amount, 'currency' => $c->currency,
                'effective_from' => $c->effective_from->toDateString(), 'effective_to' => $c->effective_to?->toDateString(),
            ])->all(),
            'reference_rates' => [
                'basis' => $context->referenceBasis,
                'amount' => $context->referenceAmount === null ? null : Decimal::format($context->referenceAmount, 8),
                'daily' => $context->dailyRate() === null ? null : Decimal::format($context->dailyRate(), 8),
                'hourly' => $context->hourlyRate() === null ? null : Decimal::format($context->hourlyRate(), 8),
            ],
            'exchange_rates' => $exchange->used(),
            'time_inputs' => $context->timeInputs,
            'leave' => $context->leave,
            'residency' => $context->residency?->only(['country_code', 'residency_status', 'effective_from']),
            'statutory_profile' => array_diff_key($context->statutoryProfile, array_flip($sensitive)),
            'rule_versions' => $context->rules->used(),
        ];
    }

    protected function componentId(string $code): ?int
    {
        $this->systemComponents ??= PayComponent::where('is_system', true)->pluck('id', 'code')->all();

        return $this->systemComponents[$code] ?? null;
    }

    /** True when an exception list contains an error. */
    public static function hasErrors(array $exceptions): bool
    {
        return collect($exceptions)->contains('severity', PayrollRunException::SEVERITY_ERROR);
    }
}
