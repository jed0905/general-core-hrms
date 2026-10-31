<?php

namespace Tests\Fixtures\Payroll;

use App\Models\PayrollRunLine;
use App\Services\Payroll\Calculation\ComponentTreatment;
use App\Services\Payroll\Calculation\PayLine;
use App\Services\Payroll\Calculation\PayrollContext;
use App\Services\Payroll\Calculation\PremiumRule;
use App\Services\Payroll\Calculation\PremiumRuleSet;
use App\Services\Payroll\Contracts\MapsAttendanceToPremiums;
use App\Services\Payroll\Contracts\PayrollLocalization;
use App\Services\Payroll\Rules\Methods\ProgressiveTable;
use App\Services\Payroll\Rules\Methods\RateWithFloorCeiling;
use App\Services\Payroll\Support\Decimal;

/**
 * A FICTIONAL country ("XX", a user-assigned ISO code) used to prove the Global
 * Payroll Core works for any country. Its rules and every value in its tests
 * are invented and represent no real law.
 *
 * - XX.SOCIAL.CONTRIBUTION (rate_with_floor_ceiling): employee_rate / employer_rate
 *   on contributory earnings clamped to floor/ceiling.
 * - XX.TAX.WITHHOLDING (progressive_table, variant = pay frequency): on taxable
 *   earnings minus the employee contribution.
 * - XX.PREMIUM (multiplier parameters "overtime", "night").
 */
class FictionalLocalization implements MapsAttendanceToPremiums, PayrollLocalization
{
    public function countryCode(): string
    {
        return 'XX';
    }

    public function name(): string
    {
        return 'Fictional test country';
    }

    public function taxTreatments(): array
    {
        return ['taxable' => 'Taxable', 'exempt' => 'Exempt'];
    }

    public function contributionTreatments(): array
    {
        return ['contributory' => 'Contributory', 'excluded' => 'Excluded'];
    }

    public function premiumCategories(): array
    {
        return ['overtime' => 'Overtime', 'night' => 'Night work'];
    }

    public function statutoryProfileFields(): array
    {
        return [
            'tax_code' => ['label' => 'Tax code', 'rules' => ['nullable', 'string', 'max:10']],
            'social_number' => ['label' => 'Social number', 'rules' => ['nullable', 'string', 'max:20'], 'sensitive' => true],
        ];
    }

    public function ruleCatalog(): array
    {
        return [
            'XX.SOCIAL.CONTRIBUTION' => ['category' => 'social_insurance', 'methods' => [RateWithFloorCeiling::METHOD], 'description' => 'Fictional social contribution'],
            'XX.TAX.WITHHOLDING' => ['category' => 'income_tax', 'methods' => [ProgressiveTable::METHOD], 'description' => 'Fictional withholding', 'variants' => ['weekly', 'biweekly', 'semi_monthly', 'monthly']],
            'XX.PREMIUM' => ['category' => 'overtime', 'methods' => ['multiplier'], 'description' => 'Fictional premium multipliers'],
        ];
    }

    public function classify(PayrollContext $context, array $lines): ComponentTreatment
    {
        $contributory = '0';
        $taxable = '0';

        foreach ($lines as $line) {
            if (! $line->isEarning()) {
                continue;
            }
            $line->taxTreatment ??= 'taxable';
            $line->contributionTreatment ??= 'contributory';

            if ($line->contributionTreatment === 'contributory') {
                $contributory = Decimal::add($contributory, $line->amount);
            }
            if ($line->taxTreatment === 'taxable') {
                $taxable = Decimal::add($taxable, $line->amount);
            }
        }

        return new ComponentTreatment(['contributory' => $contributory, 'taxable' => $taxable], $taxable);
    }

    public function statutoryLines(PayrollContext $context, ComponentTreatment $treatment): array
    {
        if (! $context->isRegularRun()) {
            return [];
        }

        $rule = $context->requireRule('XX', 'XX.SOCIAL.CONTRIBUTION');
        $employee = RateWithFloorCeiling::apply($treatment->base('contributory'), $rule->parameter('employee_rate'), $rule->parameter('floor'), $rule->parameter('ceiling'));
        $employer = RateWithFloorCeiling::apply($treatment->base('contributory'), $rule->parameter('employer_rate'), $rule->parameter('floor'), $rule->parameter('ceiling'));

        return [
            new PayLine(PayrollRunLine::CLASS_EMPLOYEE_DEDUCTION, 'XX_SOCIAL_EE', 'Social contribution (employee)', $employee['amount'], ruleVersionId: $rule->id, inputs: ['base' => $employee['base']], roundingMode: $rule->rounding_mode),
            new PayLine(PayrollRunLine::CLASS_EMPLOYER_CONTRIBUTION, 'XX_SOCIAL_ER', 'Social contribution (employer)', $employer['amount'], ruleVersionId: $rule->id, inputs: ['base' => $employer['base']], roundingMode: $rule->rounding_mode),
        ];
    }

    public function taxLines(PayrollContext $context, ComponentTreatment $treatment, array $statutoryLines): array
    {
        $employeeContributions = Decimal::sum(array_map(
            fn (PayLine $l) => $l->amount,
            array_filter($statutoryLines, fn (PayLine $l) => $l->lineClass === PayrollRunLine::CLASS_EMPLOYEE_DEDUCTION)
        ));
        $base = Decimal::max(Decimal::sub($treatment->base('taxable'), $employeeContributions), '0');
        $treatment->taxableIncome = $base;

        $rule = $context->requireRule('XX', 'XX.TAX.WITHHOLDING', variant: $context->payGroup->pay_frequency);
        $tax = ProgressiveTable::compute($rule, $base);

        return $tax && ! Decimal::isZero($tax['amount'])
            ? [new PayLine(PayrollRunLine::CLASS_EMPLOYEE_TAX, 'XX_TAX', 'Income tax', $tax['amount'], ruleVersionId: $rule->id, inputs: ['base' => $base])]
            : [];
    }

    public function premiumRules(PayrollContext $context): PremiumRuleSet
    {
        $rule = $context->rule('XX', 'XX.PREMIUM');
        if (! $rule) {
            return new PremiumRuleSet;
        }

        return new PremiumRuleSet(collect($this->premiumCategories())
            ->filter(fn ($label, $category) => $rule->parameter($category) !== null)
            ->map(fn ($label, $category) => new PremiumRule($category, $label, $rule->parameter($category), $rule->id))
            ->values()
            ->all());
    }

    /** Fictional mapping: overtime -> "overtime", night -> "night", nothing else. */
    public function attendancePremiumCategory(string $dayType, ?string $holidayType, bool $overtime, bool $night): ?string
    {
        return $overtime ? 'overtime' : ($night ? 'night' : null);
    }

    public function payslipRequirements(): array
    {
        return ['Employer name', 'Pay period', 'Gross pay', 'Deductions', 'Net pay'];
    }
}
