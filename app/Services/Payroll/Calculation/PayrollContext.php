<?php

namespace App\Services\Payroll\Calculation;

use App\Models\Employee;
use App\Models\EmployeeTaxResidency;
use App\Models\EmploymentAssignment;
use App\Models\LegalEntity;
use App\Models\PayGroup;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunException;
use App\Models\PayrollRunLine;
use App\Models\StatutoryRuleVersion;
use App\Services\Payroll\Rules\StatutoryRuleResolver;
use App\Services\Payroll\Support\Decimal;
use App\Services\Payroll\Support\RateConverter;
use Carbon\Carbon;

/**
 * Everything one employee's calculation in one run may read. Built by
 * PayrollCalculator; localizations read it and report problems through
 * error()/warning(). Rates are in the pay currency, unrounded.
 */
final class PayrollContext
{
    /** @var array<int, array{severity: string, code: string, message: string}> */
    public array $exceptions = [];

    /**
     * @param  array<string, mixed>  $statutoryProfile  localization-defined attributes
     * @param  array<string, string>  $timeInputs  approved quantities by category
     * @param  array{paid_days: string, unpaid_days: string, dates: array<int, array<string, mixed>>}  $leave
     */
    public function __construct(
        public readonly PayrollRun $run,
        public readonly PayrollPeriod $period,
        public readonly PayGroup $payGroup,
        public readonly LegalEntity $legalEntity,
        public readonly EmploymentAssignment $assignment,
        public readonly Employee $employee,
        public readonly string $currency,
        public readonly int $minorUnits,
        public readonly RateConverter $converter,
        public readonly StatutoryRuleResolver $rules,
        public readonly ?EmployeeTaxResidency $residency,
        public readonly array $statutoryProfile,
        public readonly array $timeInputs,
        public readonly array $leave,
        public ?string $referenceAmount = null,
        public ?string $referenceBasis = null,
    ) {}

    /** The payroll jurisdiction's country (selects the localization). */
    public function countryCode(): string
    {
        return $this->assignment->payroll_country_code;
    }

    /** Subdivision of the work location, for jurisdiction-specific rules. */
    public function jurisdictionCode(): ?string
    {
        return $this->assignment->work_subdivision_code;
    }

    /** The date that selects a rule version, per the rule's applies_by. */
    public function ruleDate(string $appliesBy = 'pay_date'): string
    {
        return match ($appliesBy) {
            'period_start' => $this->period->period_start->toDateString(),
            'period_end', 'earned_date' => $this->period->period_end->toDateString(),
            default => $this->period->pay_date->toDateString(),
        };
    }

    /**
     * The rule version in effect for this payslip, each version being selected by
     * the date its own applies_by names. Null when none exists.
     */
    public function rule(string $country, string $ruleKey, ?string $jurisdiction = null, ?string $variant = null): ?StatutoryRuleVersion
    {
        return $this->rules->find($country, $ruleKey, fn (string $appliesBy) => $this->ruleDate($appliesBy), $jurisdiction, $variant);
    }

    /** Like rule(), but a missing version stops the calculation (MissingStatutoryRuleException). */
    public function requireRule(string $country, string $ruleKey, ?string $jurisdiction = null, ?string $variant = null): StatutoryRuleVersion
    {
        return $this->rules->require($country, $ruleKey, fn (string $appliesBy) => $this->ruleDate($appliesBy), $jurisdiction, $variant);
    }

    public function isRegularRun(): bool
    {
        return $this->run->run_type === PayrollRun::TYPE_REGULAR;
    }

    /** Company policy stored on the legal entity for the localization to read. */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->legalEntity->localization_settings ?? [], $key, $default);
    }

    public function dailyRate(): ?string
    {
        return $this->referenceAmount === null ? null : $this->converter->dailyRate($this->referenceAmount, $this->referenceBasis);
    }

    public function hourlyRate(): ?string
    {
        return $this->referenceAmount === null ? null : $this->converter->hourlyRate($this->referenceAmount, $this->referenceBasis);
    }

    /** Monthly equivalent of the reference compensation, in the pay currency. */
    public function monthlyRate(): ?string
    {
        return $this->referenceAmount === null ? null : $this->converter->monthly($this->referenceAmount, $this->referenceBasis);
    }

    /** @return array{0: string, 1: string} first and last day of the pay date's month */
    public function payMonth(): array
    {
        $payDate = Carbon::parse($this->period->pay_date);

        return [$payDate->copy()->startOfMonth()->toDateString(), $payDate->copy()->endOfMonth()->toDateString()];
    }

    /** Number of this pay group's periods paid in the same month as this one. */
    public function periodsInPayMonth(): int
    {
        [$from, $to] = $this->payMonth();

        return max(1, PayrollPeriod::where('pay_group_id', $this->payGroup->id)->whereBetween('pay_date', [$from, $to])->count());
    }

    /** 1-based position of this period among the periods paid in its month. */
    public function positionInPayMonth(): int
    {
        [$from] = $this->payMonth();

        return PayrollPeriod::where('pay_group_id', $this->payGroup->id)
            ->whereBetween('pay_date', [$from, $this->period->pay_date->toDateString()])
            ->where(fn ($q) => $q->where('pay_date', '<', $this->period->pay_date->toDateString())->orWhere('period_start', '<=', $this->period->period_start->toDateString()))
            ->count();
    }

    /**
     * Sum of this employee's finalized line amounts with a code, from this legal
     * entity's other runs paid between two dates (reversals included, so a
     * reversed run nets to zero). Used for monthly spreading and cumulative tax.
     */
    public function priorFinalizedAmount(string $code, string $payDateFrom, string $payDateTo, ?string $lineClass = null): string
    {
        $amounts = PayrollRunLine::query()
            ->where('code', $code)
            ->when($lineClass, fn ($q) => $q->where('line_class', $lineClass))
            ->where('payroll_run_id', '<>', $this->run->id)
            ->whereHas('payslip', fn ($q) => $q->where('employee_id', $this->employee->id))
            ->whereHas('run', fn ($q) => $q
                ->where('status', PayrollRun::STATUS_FINALIZED)
                ->where('legal_entity_id', $this->legalEntity->id)
                ->whereHas('period', fn ($p) => $p->whereBetween('pay_date', [$payDateFrom, $payDateTo])))
            ->pluck('amount');

        return Decimal::sum($amounts->map(fn ($amount) => (string) $amount));
    }

    /**
     * Sum of this employee's finalized lines of a class carrying a treatment key
     * (e.g. contributory earnings) from this legal entity's other runs paid
     * between two dates.
     */
    public function priorFinalizedTreatmentAmount(string $treatmentColumn, array $treatments, string $lineClass, string $payDateFrom, string $payDateTo): string
    {
        $amounts = PayrollRunLine::query()
            ->whereIn($treatmentColumn === 'tax' ? 'tax_treatment' : 'contribution_treatment', $treatments)
            ->where('line_class', $lineClass)
            ->where('payroll_run_id', '<>', $this->run->id)
            ->whereHas('payslip', fn ($q) => $q->where('employee_id', $this->employee->id))
            ->whereHas('run', fn ($q) => $q
                ->where('status', PayrollRun::STATUS_FINALIZED)
                ->where('legal_entity_id', $this->legalEntity->id)
                ->whereHas('period', fn ($p) => $p->whereBetween('pay_date', [$payDateFrom, $payDateTo])))
            ->pluck('amount');

        return Decimal::sum($amounts->map(fn ($amount) => (string) $amount));
    }

    public function error(string $code, string $message): void
    {
        $this->exceptions[] = ['severity' => PayrollRunException::SEVERITY_ERROR, 'code' => $code, 'message' => $message];
    }

    public function warning(string $code, string $message): void
    {
        $this->exceptions[] = ['severity' => PayrollRunException::SEVERITY_WARNING, 'code' => $code, 'message' => $message];
    }

    public function hasErrors(): bool
    {
        return collect($this->exceptions)->contains('severity', PayrollRunException::SEVERITY_ERROR);
    }
}
