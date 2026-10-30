<?php

namespace Tests\Feature\Payroll\Localizations\PH;

use App\Models\EmploymentAssignment;
use App\Models\LegalEntity;
use App\Models\PayGroup;
use App\Models\StatutoryRuleVersion;
use Tests\Feature\Payroll\PayrollTestCase;

/**
 * Philippines localization suite. EVERY statutory value below is FICTIONAL and
 * chosen to make arithmetic obvious; none is a real SSS, PhilHealth, Pag-IBIG,
 * BIR or DOLE value. The tests prove the PH calculation methods, not the law.
 *
 * Fictional rules (all effective 2026-01-01, verified):
 * - PH.SSS.CONTRIBUTION brackets: from 0 -> EE 100 / ER 200 / EC 5; from 20000 -> EE 300 / ER 600 / EC 15
 * - PH.PHILHEALTH.PREMIUM: rate 0.04, floor 8000, ceiling 60000, employee_share 0.5
 * - PH.HDMF.CONTRIBUTION brackets: from 0 -> 1% / 2%; from 2000 -> 2% / 2%; max_fund_salary 6000
 * - PH.BIR.WITHHOLDING monthly: 0 below 15000, then 20% of the excess over 15000;
 *   semi_monthly: 0 below 7500, then 20% of the excess over 7500
 * - PH.BIR.NON_TAXABLE: benefits_exemption_ceiling 50000
 * - PH.DOLE.PREMIUM_PAY: overtime 1.3
 * Pay groups use a configured 8-hour day and 260 working days (company setting).
 */
abstract class PhilippinesTestCase extends PayrollTestCase
{
    protected LegalEntity $phEntity;

    protected PayGroup $monthly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->phEntity = $this->legalEntity(['code' => 'PHCO', 'name' => 'PH Co', 'country_code' => 'PH', 'functional_currency' => 'PHP', 'timezone' => 'Asia/Manila', 'locale' => 'en-PH']);
        $this->monthly = $this->phPayGroup('monthly');
        $this->fictionalPhRules();
    }

    protected function phPayGroup(string $frequency, array $attributes = []): PayGroup
    {
        return $this->payGroup($this->phEntity, array_merge([
            'payroll_country_code' => 'PH',
            'pay_frequency' => $frequency,
            'pay_currency' => 'PHP',
        ], $attributes));
    }

    protected function phEmployee(PayGroup $group, string $amount = '30000.00', string $basis = 'monthly', array $assignment = []): EmploymentAssignment
    {
        return $this->employeeOn($group, $basis, $amount, 'PHP', assignment: $assignment);
    }

    protected function phRule(string $key, string $method, array $parameters = [], array $brackets = [], array $attributes = []): StatutoryRuleVersion
    {
        return $this->rule($key, $method, $parameters, $brackets, array_merge(['country_code' => 'PH', 'official_source' => 'FICTIONAL test fixture'], $attributes));
    }

    protected function fictionalPhRules(): void
    {
        $this->phRule('PH.SSS.CONTRIBUTION', 'bracket_lookup', [], [
            ['range_from' => '0', 'employee_amount' => '100', 'employer_amount' => '200', 'employer_extra_amount' => '5'],
            ['range_from' => '20000', 'employee_amount' => '300', 'employer_amount' => '600', 'employer_extra_amount' => '15'],
        ]);
        $this->phRule('PH.PHILHEALTH.PREMIUM', 'rate_with_floor_ceiling', ['rate' => '0.04', 'floor' => '8000', 'ceiling' => '60000', 'employee_share' => '0.5'], [], ['category' => 'healthcare']);
        $this->phRule('PH.HDMF.CONTRIBUTION', 'bracket_lookup', ['max_fund_salary' => '6000'], [
            ['range_from' => '0', 'employee_rate' => '0.01', 'employer_rate' => '0.02'],
            ['range_from' => '2000', 'employee_rate' => '0.02', 'employer_rate' => '0.02'],
        ], ['category' => 'pension']);
        $this->phRule('PH.BIR.WITHHOLDING', 'progressive_table', [], [
            ['range_from' => '0', 'fixed_amount' => '0', 'rate' => '0'],
            ['range_from' => '15000', 'fixed_amount' => '0', 'rate' => '0.20', 'excess_over' => '15000'],
        ], ['category' => 'income_tax', 'variant' => 'monthly']);
        $this->phRule('PH.BIR.WITHHOLDING', 'progressive_table', [], [
            ['range_from' => '0', 'fixed_amount' => '0', 'rate' => '0'],
            ['range_from' => '7500', 'fixed_amount' => '0', 'rate' => '0.20', 'excess_over' => '7500'],
        ], ['category' => 'income_tax', 'variant' => 'semi_monthly']);
        $this->phRule('PH.BIR.NON_TAXABLE', 'parameters', ['benefits_exemption_ceiling' => '50000'], [], ['category' => 'income_tax']);
        $this->phRule('PH.DOLE.PREMIUM_PAY', 'multiplier', ['overtime' => '1.3'], [], ['category' => 'overtime']);
    }
}
