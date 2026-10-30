<?php

namespace App\Services\Payroll\Localizations\PH;

use App\Models\PayrollRunLine;
use App\Models\StatutoryRuleVersion;
use App\Services\Payroll\Calculation\ComponentTreatment;
use App\Services\Payroll\Calculation\PayLine;
use App\Services\Payroll\Calculation\PayrollContext;
use App\Services\Payroll\Calculation\PremiumRule;
use App\Services\Payroll\Calculation\PremiumRuleSet;
use App\Services\Payroll\Contracts\PayrollLocalization;
use App\Services\Payroll\Rules\Methods\BracketLookup;
use App\Services\Payroll\Rules\Methods\ProgressiveTable;
use App\Services\Payroll\Rules\Methods\RateWithFloorCeiling;
use App\Services\Payroll\Support\Decimal;

/**
 * Philippines (PH) payroll localization.
 *
 * Contains NO statutory values: every rate, bracket, ceiling, multiplier and
 * exemption amount is read from a PH.* statutory rule version entered with its
 * official source (see docs/development/payroll/PH_LOCALIZATION.md). A missing
 * rule stops the employee's calculation with an error; nothing is defaulted.
 *
 * Contribution timing for non-monthly pay groups is COMPANY POLICY, read from
 * the legal entity's localization_settings "ph.contribution_schedule":
 * "last_period" (whole monthly amount in the last period paid in the month) or
 * "split" (spread evenly; the last period trues up to the monthly total).
 */
class PhilippinesLocalization implements PayrollLocalization
{
    public const COUNTRY = 'PH';

    public const SSS = 'PH.SSS.CONTRIBUTION';

    public const PHILHEALTH = 'PH.PHILHEALTH.PREMIUM';

    public const HDMF = 'PH.HDMF.CONTRIBUTION';

    public const WITHHOLDING = 'PH.BIR.WITHHOLDING';

    public const NON_TAXABLE = 'PH.BIR.NON_TAXABLE';

    public const PREMIUM = 'PH.DOLE.PREMIUM_PAY';

    public const MINIMUM_WAGE = 'PH.NWPC.MINIMUM_WAGE';

    /** Contribution treatments: which statutory bases a component counts toward. */
    public const CONTRIBUTION_BASIC = 'basic';               // SSS, HDMF and PhilHealth (basic salary)

    public const CONTRIBUTION_COMPENSATION = 'compensation'; // SSS and HDMF only

    public const CONTRIBUTION_EXCLUDED = 'excluded';

    public const TAX_TAXABLE = 'taxable';

    public const TAX_NON_TAXABLE = 'non_taxable';

    public const TAX_BENEFITS = 'thirteenth_month_other_benefits';

    /** Engine-emitted pay components and their treatment unless configured otherwise. */
    private const SYSTEM_DEFAULTS = [
        'BASIC' => [self::TAX_TAXABLE, self::CONTRIBUTION_BASIC],
        'PAID_LEAVE' => [self::TAX_TAXABLE, self::CONTRIBUTION_BASIC],
        // PhilHealth's monthly basic salary is not reduced by absences, tardiness, undertime
        // or unpaid leave (PhilHealth Advisory 2025-0002), so these count toward SSS/HDMF only.
        'ABSENCE' => [self::TAX_TAXABLE, self::CONTRIBUTION_COMPENSATION],
        'LWOP' => [self::TAX_TAXABLE, self::CONTRIBUTION_COMPENSATION],
        'LATE' => [self::TAX_TAXABLE, self::CONTRIBUTION_COMPENSATION],
        'UNDERTIME' => [self::TAX_TAXABLE, self::CONTRIBUTION_COMPENSATION],
        'PREMIUM' => [self::TAX_TAXABLE, self::CONTRIBUTION_COMPENSATION],
    ];

    /**
     * Statutory-minimum-wage pay that a minimum wage earner's withholding excludes
     * (basic pay and premium time). Applied only when the employee's PH profile
     * marks them a minimum wage earner; the legal basis is listed in PH_LOCALIZATION.md.
     */
    private const MWE_EXCLUDED_PREFIXES = ['BASIC', 'PAID_LEAVE', 'ABSENCE', 'LWOP', 'LATE', 'UNDERTIME', 'PREMIUM_'];

    public function countryCode(): string
    {
        return self::COUNTRY;
    }

    public function name(): string
    {
        return 'Philippines';
    }

    public function taxTreatments(): array
    {
        return [
            self::TAX_TAXABLE => 'Taxable compensation',
            self::TAX_NON_TAXABLE => 'Non-taxable (cite the exemption on the component)',
            self::TAX_BENEFITS => '13th month pay and other benefits (exempt up to the annual ceiling)',
        ];
    }

    public function contributionTreatments(): array
    {
        return [
            self::CONTRIBUTION_BASIC => 'Basic salary (SSS, Pag-IBIG and PhilHealth bases)',
            self::CONTRIBUTION_COMPENSATION => 'Compensation (SSS and Pag-IBIG bases only)',
            self::CONTRIBUTION_EXCLUDED => 'Not contributory',
        ];
    }

    public function premiumCategories(): array
    {
        return [
            // Each category's multiplier is the full rate for those hours relative to the
            // regular hourly rate, as in the DOLE Handbook guide tables (premiums compound).
            'overtime' => 'Overtime',
            'night_differential' => 'Night shift differential',
            'overtime_night' => 'Overtime at night',
            'rest_day' => 'Rest day work',
            'rest_day_overtime' => 'Rest day overtime',
            'rest_day_night' => 'Rest day night shift',
            'special_holiday' => 'Special non-working day work',
            'special_holiday_overtime' => 'Special non-working day overtime',
            'special_holiday_night' => 'Special non-working day night shift',
            'special_holiday_rest_day' => 'Special day falling on rest day',
            'regular_holiday' => 'Regular holiday work',
            'regular_holiday_overtime' => 'Regular holiday overtime',
            'regular_holiday_night' => 'Regular holiday night shift',
            'regular_holiday_rest_day' => 'Regular holiday falling on rest day',
            'double_holiday' => 'Double holiday work',
        ];
    }

    public function statutoryProfileFields(): array
    {
        return [
            'tin' => ['label' => 'TIN', 'rules' => ['nullable', 'string', 'max:20'], 'sensitive' => true],
            'sss_number' => ['label' => 'SSS number', 'rules' => ['nullable', 'string', 'max:20'], 'sensitive' => true],
            'philhealth_number' => ['label' => 'PhilHealth number', 'rules' => ['nullable', 'string', 'max:20'], 'sensitive' => true],
            'hdmf_number' => ['label' => 'Pag-IBIG (HDMF) MID number', 'rules' => ['nullable', 'string', 'max:20'], 'sensitive' => true],
            'minimum_wage_earner' => ['label' => 'Minimum wage earner', 'rules' => ['nullable', 'boolean'], 'options' => ['1' => 'Yes', '0' => 'No']],
            'hdmf_additional_employee_amount' => ['label' => 'Voluntary additional Pag-IBIG (per month)', 'rules' => ['nullable', 'numeric', 'min:0']],
        ];
    }

    public function ruleCatalog(): array
    {
        return [
            self::SSS => ['category' => 'social_insurance', 'methods' => [BracketLookup::METHOD], 'description' => 'SSS contribution schedule: monthly salary credit brackets with employee share, employer share and employer-only EC amount (employee_amount, employer_amount, employer_extra_amount).'],
            self::PHILHEALTH => ['category' => 'healthcare', 'methods' => [RateWithFloorCeiling::METHOD], 'description' => 'PhilHealth premium: parameters rate, floor, ceiling and employee_share (fraction of the premium paid by the employee) on monthly basic salary.'],
            self::HDMF => ['category' => 'pension', 'methods' => [BracketLookup::METHOD], 'description' => 'Pag-IBIG contribution: brackets with employee_rate / employer_rate; parameter max_fund_salary caps the base.'],
            self::WITHHOLDING => ['category' => 'income_tax', 'methods' => [ProgressiveTable::METHOD], 'description' => 'BIR withholding tax table for one payroll frequency (variant): fixed_amount + rate on the excess over excess_over.', 'variants' => ['daily', 'weekly', 'semi_monthly', 'monthly']],
            self::NON_TAXABLE => ['category' => 'income_tax', 'methods' => ['parameters'], 'description' => 'Parameter benefits_exemption_ceiling: annual exempt amount of 13th month pay and other benefits.'],
            self::PREMIUM => ['category' => 'overtime', 'methods' => ['multiplier'], 'description' => 'Premium pay multipliers of the hourly rate, one parameter per premium category (overtime, night_differential, rest_day, ...).'],
            self::MINIMUM_WAGE => ['category' => 'minimum_wage', 'methods' => ['parameters'], 'description' => 'Regional wage order: parameter daily_rate per wage region (jurisdiction_code = work subdivision). Validates rates; never calculates pay.'],
        ];
    }

    public function classify(PayrollContext $context, array $lines): ComponentTreatment
    {
        $basic = '0';
        $compensation = '0';
        $taxable = '0';
        $benefits = '0';
        $mwe = (bool) ($context->statutoryProfile['minimum_wage_earner'] ?? false);

        foreach ($lines as $line) {
            if (! $line->isEarning()) {
                continue;
            }

            $defaults = self::SYSTEM_DEFAULTS[str_starts_with($line->code, 'PREMIUM_') ? 'PREMIUM' : $line->code] ?? null;
            $line->taxTreatment ??= $defaults[0] ?? null;
            $line->contributionTreatment ??= $defaults[1] ?? null;

            if ($line->taxTreatment === null || $line->contributionTreatment === null) {
                $context->error('PH_TREATMENT_MISSING', "Pay component {$line->code} has no Philippine tax/contribution treatment. Configure it in Payroll > Pay Components.");

                continue;
            }

            if (in_array($line->contributionTreatment, [self::CONTRIBUTION_BASIC, self::CONTRIBUTION_COMPENSATION], true)) {
                $compensation = Decimal::add($compensation, $line->amount);
            }
            if ($line->contributionTreatment === self::CONTRIBUTION_BASIC) {
                $basic = Decimal::add($basic, $line->amount);
            }

            $mweExcluded = $mwe && collect(self::MWE_EXCLUDED_PREFIXES)->contains(fn ($p) => str_starts_with($line->code, $p));
            if ($line->taxTreatment === self::TAX_TAXABLE && ! $mweExcluded) {
                $taxable = Decimal::add($taxable, $line->amount);
            } elseif ($line->taxTreatment === self::TAX_BENEFITS) {
                $benefits = Decimal::add($benefits, $line->amount);
            }
        }

        if (! Decimal::isZero($benefits)) {
            $taxable = Decimal::add($taxable, $this->taxableBenefits($context, $benefits));
        }

        $this->checkMinimumWage($context);

        return new ComponentTreatment(['basic' => $basic, 'compensation' => $compensation, 'taxable' => $taxable], $taxable);
    }

    public function statutoryLines(PayrollContext $context, ComponentTreatment $treatment): array
    {
        if (! $context->isRegularRun() || $context->hasErrors()) {
            return [];
        }

        $lines = [];
        foreach ($this->sss($context, $treatment) as $line) {
            $lines[] = $line;
        }
        foreach ($this->philhealth($context, $treatment) as $line) {
            $lines[] = $line;
        }
        foreach ($this->hdmf($context, $treatment) as $line) {
            $lines[] = $line;
        }

        return $lines;
    }

    public function taxLines(PayrollContext $context, ComponentTreatment $treatment, array $statutoryLines): array
    {
        if ($context->hasErrors()) {
            return [];
        }

        // Mandatory employee contributions are excluded from taxable compensation (voluntary extras are not).
        $mandatory = Decimal::sum(array_map(
            fn (PayLine $l) => $l->amount,
            array_filter($statutoryLines, fn (PayLine $l) => $l->lineClass === PayrollRunLine::CLASS_EMPLOYEE_DEDUCTION && ! ($l->inputs['voluntary'] ?? false))
        ));
        $base = Decimal::max(Decimal::sub($treatment->base('taxable'), $mandatory), '0');
        $treatment->taxableIncome = $base;

        if (! $context->isRegularRun()) {
            if (! Decimal::isZero($base)) {
                $context->warning('PH_SUPPLEMENTAL_TAX', 'Withholding on supplemental pay is not calculated automatically. Add any tax due as an adjustment after review.');
            }

            return [];
        }

        $variant = $context->payGroup->pay_frequency;
        $rule = $context->requireRule(self::COUNTRY, self::WITHHOLDING, variant: $variant);
        $tax = ProgressiveTable::compute($rule, $base);

        if (! $tax) {
            $context->error('PH_WITHHOLDING_NO_BRACKET', "No withholding bracket covers taxable compensation {$base}.");

            return [];
        }

        return Decimal::isZero($tax['amount']) ? [] : [
            new PayLine(PayrollRunLine::CLASS_EMPLOYEE_TAX, 'PH_WTAX', 'Withholding tax on compensation', $tax['amount'],
                ruleVersionId: $rule->id, inputs: ['taxable_compensation' => Decimal::format($base, 4), 'bracket_id' => $tax['bracket_id'], 'variant' => $variant],
                roundingMode: $rule->rounding_mode),
        ];
    }

    public function premiumRules(PayrollContext $context): PremiumRuleSet
    {
        $rule = $context->rule(self::COUNTRY, self::PREMIUM, $context->jurisdictionCode());
        if (! $rule) {
            return new PremiumRuleSet;
        }

        return new PremiumRuleSet(collect($this->premiumCategories())
            ->filter(fn ($label, $category) => $rule->parameter($category) !== null)
            ->map(fn ($label, $category) => new PremiumRule($category, $label, $rule->parameter($category), $rule->id))
            ->values()
            ->all());
    }

    public function payslipRequirements(): array
    {
        return [
            'Employer name and address',
            'Employee name and ID',
            'Pay period and pay date',
            'Basic pay, overtime, premium and night shift differential pay shown separately',
            'Each deduction (SSS, PhilHealth, Pag-IBIG, withholding tax, others) shown separately',
            'Gross and net pay',
        ];
    }

    /* ------------------------------------------------------------- statutory */

    /** @return array<int, PayLine> */
    private function sss(PayrollContext $context, ComponentTreatment $treatment): array
    {
        $rule = $context->requireRule(self::COUNTRY, self::SSS);

        return $this->monthly($context, $treatment->base('compensation'), [PayrollRunLine::CLASS_EARNING, ['basic', 'compensation']], function (string $monthlyBase) use ($rule, $context) {
            $bracket = BracketLookup::find($rule, $monthlyBase);
            if (! $bracket) {
                $context->error('PH_SSS_NO_BRACKET', "No SSS bracket covers monthly compensation {$monthlyBase}.");

                return [];
            }

            return [
                ['PH_SSS_EE', 'SSS contribution', PayrollRunLine::CLASS_EMPLOYEE_DEDUCTION, (string) ($bracket->employee_amount ?? '0')],
                ['PH_SSS_ER', 'SSS contribution (employer)', PayrollRunLine::CLASS_EMPLOYER_CONTRIBUTION, (string) ($bracket->employer_amount ?? '0')],
                ['PH_SSS_EC', 'SSS employees\' compensation (employer)', PayrollRunLine::CLASS_EMPLOYER_CONTRIBUTION, (string) ($bracket->employer_extra_amount ?? '0')],
            ];
        }, $rule);
    }

    /** @return array<int, PayLine> */
    private function philhealth(PayrollContext $context, ComponentTreatment $treatment): array
    {
        $rule = $context->requireRule(self::COUNTRY, self::PHILHEALTH);

        return $this->monthly($context, $treatment->base('basic'), [PayrollRunLine::CLASS_EARNING, ['basic']], function (string $monthlyBase) use ($rule, $context) {
            $rate = $rule->parameter('rate');
            $share = $rule->parameter('employee_share');
            if ($rate === null || $share === null) {
                $context->error('PH_PHILHEALTH_INCOMPLETE', 'The PhilHealth rule version needs the parameters rate and employee_share.');

                return [];
            }

            $premium = RateWithFloorCeiling::apply($monthlyBase, $rate, $rule->parameter('floor'), $rule->parameter('ceiling'))['amount'];
            $employee = Decimal::mul($premium, $share);

            return [
                ['PH_PHIC_EE', 'PhilHealth premium', PayrollRunLine::CLASS_EMPLOYEE_DEDUCTION, $employee],
                ['PH_PHIC_ER', 'PhilHealth premium (employer)', PayrollRunLine::CLASS_EMPLOYER_CONTRIBUTION, Decimal::sub($premium, $employee)],
            ];
        }, $rule);
    }

    /** @return array<int, PayLine> */
    private function hdmf(PayrollContext $context, ComponentTreatment $treatment): array
    {
        $rule = $context->requireRule(self::COUNTRY, self::HDMF);

        $lines = $this->monthly($context, $treatment->base('compensation'), [PayrollRunLine::CLASS_EARNING, ['basic', 'compensation']], function (string $monthlyBase) use ($rule, $context) {
            $bracket = BracketLookup::find($rule, $monthlyBase);
            if (! $bracket) {
                $context->error('PH_HDMF_NO_BRACKET', "No Pag-IBIG bracket covers monthly compensation {$monthlyBase}.");

                return [];
            }
            $capped = $rule->parameter('max_fund_salary') === null ? $monthlyBase : Decimal::min($monthlyBase, $rule->parameter('max_fund_salary'));

            return [
                ['PH_HDMF_EE', 'Pag-IBIG contribution', PayrollRunLine::CLASS_EMPLOYEE_DEDUCTION, Decimal::mul($capped, (string) ($bracket->employee_rate ?? '0'))],
                ['PH_HDMF_ER', 'Pag-IBIG contribution (employer)', PayrollRunLine::CLASS_EMPLOYER_CONTRIBUTION, Decimal::mul($capped, (string) ($bracket->employer_rate ?? '0'))],
            ];
        }, $rule);

        $additional = $context->statutoryProfile['hdmf_additional_employee_amount'] ?? null;
        if ($additional !== null && $additional !== '' && $this->isContributionPeriod($context)) {
            $lines[] = new PayLine(PayrollRunLine::CLASS_EMPLOYEE_DEDUCTION, 'PH_HDMF_EE_VOL', 'Pag-IBIG voluntary additional', Decimal::of((string) $additional), inputs: ['voluntary' => true]);
        }

        return $lines;
    }

    /**
     * Monthly statutory amounts applied to this period per the entity's
     * contribution schedule. $compute receives the monthly base and returns
     * [code, description, class, monthly amount] rows.
     *
     * @param  array{0: string, 1: array<int, string>}  $baseLines  line class and contribution treatments that make up the base
     * @return array<int, PayLine>
     */
    private function monthly(PayrollContext $context, string $currentBase, array $baseLines, callable $compute, StatutoryRuleVersion $rule): array
    {
        $periods = $this->periodsInMonth($context);
        $position = $periods === 1 ? 1 : $context->positionInPayMonth();
        $isLast = $position >= $periods;
        $schedule = $periods === 1 ? 'last_period' : $context->setting('ph.contribution_schedule');
        [$monthFrom, $monthTo] = $context->payMonth();

        if (! in_array($schedule, ['last_period', 'split'], true)) {
            $context->error('PH_CONTRIBUTION_SCHEDULE', 'Set the legal entity\'s Philippine contribution schedule (last_period or split) for non-monthly pay groups.');

            return [];
        }
        if ($schedule === 'last_period' && ! $isLast) {
            return [];
        }

        $priorBase = $periods === 1 ? '0' : $context->priorFinalizedTreatmentAmount('contribution', $baseLines[1], $baseLines[0], $monthFrom, $monthTo);
        $monthlyBase = ($schedule === 'split' && ! $isLast)
            ? Decimal::mul($currentBase, (string) $periods)   // projection until the month is complete
            : Decimal::add($currentBase, $priorBase);

        $lines = [];
        foreach ($compute($monthlyBase) as [$code, $description, $class, $monthlyAmount]) {
            $amount = match (true) {
                $schedule === 'split' && ! $isLast => Decimal::div($monthlyAmount, (string) $periods),
                $schedule === 'split' => Decimal::sub($monthlyAmount, $context->priorFinalizedAmount($code, $monthFrom, $monthTo)),
                default => $monthlyAmount,
            };

            $lines[] = new PayLine($class, $code, $description, $amount, ruleVersionId: $rule->id, roundingMode: $rule->rounding_mode, inputs: [
                'monthly_base' => Decimal::format($monthlyBase, 4),
                'monthly_amount' => Decimal::format($monthlyAmount, 4),
                'schedule' => $schedule,
                'period_in_month' => $position.'/'.$periods,
            ]);
        }

        return $lines;
    }

    private function isContributionPeriod(PayrollContext $context): bool
    {
        $periods = $this->periodsInMonth($context);

        return $periods === 1 || $context->positionInPayMonth() >= $periods;
    }

    /** A monthly pay group contributes every period; others share the month's amount. */
    private function periodsInMonth(PayrollContext $context): int
    {
        return $context->payGroup->pay_frequency === 'monthly' ? 1 : $context->periodsInPayMonth();
    }

    /**
     * 13th month pay and other benefits are exempt up to an annual ceiling
     * (PH.BIR.NON_TAXABLE parameter benefits_exemption_ceiling); the excess is taxable.
     */
    private function taxableBenefits(PayrollContext $context, string $benefits): string
    {
        $rule = $context->requireRule(self::COUNTRY, self::NON_TAXABLE);
        $ceiling = $rule->parameter('benefits_exemption_ceiling');
        if ($ceiling === null) {
            $context->error('PH_BENEFITS_CEILING', 'The PH.BIR.NON_TAXABLE rule version needs the parameter benefits_exemption_ceiling.');

            return '0';
        }

        $year = (string) $context->period->tax_year;
        $prior = $context->priorFinalizedTreatmentAmount('tax', [self::TAX_BENEFITS], PayrollRunLine::CLASS_EARNING, "{$year}-01-01", "{$year}-12-31");
        $remainingExempt = Decimal::max(Decimal::sub($ceiling, $prior), '0');

        return Decimal::max(Decimal::sub($benefits, $remainingExempt), '0');
    }

    /** Warn (never block) when the daily rate is below the regional minimum wage rule. */
    private function checkMinimumWage(PayrollContext $context): void
    {
        $daily = $context->dailyRate();
        $region = $context->jurisdictionCode();
        if ($daily === null || $region === null) {
            return;
        }

        $rule = $context->rule(self::COUNTRY, self::MINIMUM_WAGE, $region);
        if ($rule && $rule->jurisdiction_code === $region && ($minimum = $rule->parameter('daily_rate')) !== null && Decimal::cmp($daily, $minimum) < 0) {
            $context->warning('PH_BELOW_MINIMUM_WAGE', 'The daily rate '.Decimal::format($daily, 2)." is below the minimum wage rule for {$region} ({$minimum}).");
        }
    }
}
