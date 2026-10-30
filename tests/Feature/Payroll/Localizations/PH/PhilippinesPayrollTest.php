<?php

namespace Tests\Feature\Payroll\Localizations\PH;

use App\Models\EmployeeStatutoryProfile;
use App\Models\PayComponent;
use App\Models\PayComponentTreatment;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\StatutoryRuleVersion;
use App\Services\Payroll\PayrollRunService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Philippine calculation methods with FICTIONAL rule values (see PhilippinesTestCase):
 * SSS / PhilHealth / Pag-IBIG employee and employer shares as separate lines,
 * withholding on taxable compensation net of mandatory contributions, monthly
 * contribution schedules for semi-monthly payroll, minimum wage earners, the
 * 13th-month/benefits ceiling, effective-date selection and country isolation.
 */
class PhilippinesPayrollTest extends PhilippinesTestCase
{
    #[Test]
    public function a_monthly_payslip_has_separate_employee_and_employer_statutory_lines(): void
    {
        $period = $this->period($this->monthly, '2026-10-01', '2026-10-31');
        $assignment = $this->phEmployee($this->monthly, '30000.00');

        $run = $this->calculate($this->runFor($period));
        $payslip = $run->payslips()->first();

        $this->assertSame([
            'BASIC' => '30000.0000',
            'PH_SSS_EE' => '300.0000',
            'PH_SSS_ER' => '600.0000',
            'PH_SSS_EC' => '15.0000',
            'PH_PHIC_EE' => '600.0000',
            'PH_PHIC_ER' => '600.0000',
            'PH_HDMF_EE' => '120.0000',   // 2% of the 6000 fictional max fund salary
            'PH_HDMF_ER' => '120.0000',
            'PH_WTAX' => '2796.0000',     // (30000 - 1020 - 15000) x 20%
        ], $this->linesOf($run, $assignment));
        $this->assertSame('1020.0000', $payslip->employee_deductions);
        $this->assertSame('26184.0000', $payslip->net_pay);
        $this->assertSame('1335.0000', $payslip->employer_contributions);
        $this->assertSame('31335.0000', $payslip->employer_cost);
        $this->assertSame('28980.0000', $payslip->taxable_income);
        $this->assertFalse($run->hasBlockingExceptions());
    }

    #[Test]
    public function absences_reduce_the_sss_base_but_not_the_philhealth_basic_salary(): void
    {
        $period = $this->period($this->monthly, '2026-10-01', '2026-10-31');
        $assignment = $this->phEmployee($this->monthly, '30000.00');
        $this->timeInput($period, $assignment, 'absence_days', '2');

        $run = $this->calculate($this->runFor($period));
        $inputs = $run->lines()->pluck('inputs', 'code');

        $this->assertSame('27230.7700', $inputs['PH_SSS_EE']['monthly_base']);
        $this->assertSame('30000.0000', $inputs['PH_PHIC_EE']['monthly_base']);
    }

    #[Test]
    public function a_semi_monthly_last_period_schedule_deducts_the_whole_month_in_the_last_period(): void
    {
        $this->phEntity->update(['localization_settings' => ['ph' => ['contribution_schedule' => 'last_period']]]);
        $group = $this->phPayGroup('semi_monthly');
        $first = $this->period($group, '2026-10-01', '2026-10-15', '2026-10-15', 1);
        $second = $this->period($group, '2026-10-16', '2026-10-31', '2026-10-31', 2);
        $assignment = $this->phEmployee($group, '30000.00');

        $firstRun = $this->finalize($this->runFor($first));
        $firstLines = $this->linesOf($firstRun, $assignment);
        $this->assertArrayNotHasKey('PH_SSS_EE', $firstLines);
        $this->assertSame('1500.0000', $firstLines['PH_WTAX']); // (15000 - 7500) x 20%

        $secondLines = $this->linesOf($this->calculate($this->runFor($second)), $assignment);
        $this->assertSame('300.0000', $secondLines['PH_SSS_EE']);   // monthly base 15000 + 15000
        $this->assertSame('600.0000', $secondLines['PH_PHIC_EE']);
        $this->assertSame('120.0000', $secondLines['PH_HDMF_EE']);
        $this->assertSame('1296.0000', $secondLines['PH_WTAX']); // (15000 - 1020 - 7500) x 20%
    }

    #[Test]
    public function a_semi_monthly_split_schedule_spreads_the_month_and_trues_up_in_the_last_period(): void
    {
        $this->phEntity->update(['localization_settings' => ['ph' => ['contribution_schedule' => 'split']]]);
        $group = $this->phPayGroup('semi_monthly');
        $first = $this->period($group, '2026-10-01', '2026-10-15', '2026-10-15', 1);
        $second = $this->period($group, '2026-10-16', '2026-10-31', '2026-10-31', 2);
        $assignment = $this->phEmployee($group, '30000.00');

        $firstLines = $this->linesOf($this->finalize($this->runFor($first)), $assignment);
        $this->assertSame('150.0000', $firstLines['PH_SSS_EE']);
        $this->assertSame('7.5000', $firstLines['PH_SSS_EC']);
        $this->assertSame('300.0000', $firstLines['PH_PHIC_EE']);

        $secondLines = $this->linesOf($this->calculate($this->runFor($second)), $assignment);
        $this->assertSame('150.0000', $secondLines['PH_SSS_EE']); // monthly 300 - 150 already deducted
        $this->assertSame('300.0000', $secondLines['PH_PHIC_EE']);
    }

    #[Test]
    public function a_semi_monthly_group_without_a_contribution_schedule_is_an_error(): void
    {
        $group = $this->phPayGroup('semi_monthly');
        $period = $this->period($group, '2026-10-01', '2026-10-15', '2026-10-15', 1);
        $this->period($group, '2026-10-16', '2026-10-31', '2026-10-31', 2);
        $this->phEmployee($group);

        $run = $this->calculate($this->runFor($period));

        $this->assertTrue($run->exceptions()->where('code', 'PH_CONTRIBUTION_SCHEDULE')->exists());
    }

    #[Test]
    public function a_minimum_wage_earner_has_no_withholding_on_basic_pay(): void
    {
        $period = $this->period($this->monthly, '2026-10-01', '2026-10-31');
        $assignment = $this->phEmployee($this->monthly, '30000.00');
        EmployeeStatutoryProfile::create(['employee_id' => $assignment->employee_id, 'country_code' => 'PH', 'fields' => ['minimum_wage_earner' => true], 'effective_from' => '2026-01-01']);

        $run = $this->calculate($this->runFor($period));

        $this->assertArrayNotHasKey('PH_WTAX', $this->linesOf($run, $assignment));
        $this->assertSame('300.0000', $this->linesOf($run, $assignment)['PH_SSS_EE']); // contributions still apply
        $this->assertSame('0.0000', $run->payslips()->first()->taxable_income);
    }

    #[Test]
    public function benefits_are_exempt_up_to_the_annual_ceiling_and_the_excess_is_taxed(): void
    {
        $period = $this->period($this->monthly, '2026-10-01', '2026-10-31');
        $assignment = $this->phEmployee($this->monthly, '30000.00');
        $bonus = PayComponent::create(['code' => 'THIRTEENTH', 'name' => '13th month pay', 'line_class' => 'earning', 'category' => 'bonus']);
        PayComponentTreatment::create(['pay_component_id' => $bonus->id, 'country_code' => 'PH', 'tax_treatment' => 'thirteenth_month_other_benefits', 'contribution_treatment' => 'excluded']);
        PayrollAdjustment::create(['payroll_period_id' => $period->id, 'employee_id' => $assignment->employee_id, 'pay_component_id' => $bonus->id, 'amount' => '60000.00', 'currency' => 'PHP', 'status' => 'approved']);

        $lines = $this->linesOf($this->calculate($this->runFor($period)), $assignment);

        $this->assertSame('300.0000', $lines['PH_SSS_EE']); // not contributory
        $this->assertSame('4796.0000', $lines['PH_WTAX']); // (30000 - 1020 + 10000 excess - 15000) x 20%
    }

    #[Test]
    public function a_component_without_a_philippine_treatment_blocks_the_payslip(): void
    {
        $period = $this->period($this->monthly, '2026-10-01', '2026-10-31');
        $assignment = $this->phEmployee($this->monthly);
        $allowance = PayComponent::create(['code' => 'RICE', 'name' => 'Rice allowance', 'line_class' => 'earning', 'category' => 'allowance']);
        PayrollAdjustment::create(['payroll_period_id' => $period->id, 'employee_id' => $assignment->employee_id, 'pay_component_id' => $allowance->id, 'amount' => '1000.00', 'currency' => 'PHP', 'status' => 'approved']);

        $run = $this->calculate($this->runFor($period));

        $this->assertTrue($run->exceptions()->where('code', 'PH_TREATMENT_MISSING')->exists());
        $this->assertArrayNotHasKey('PH_WTAX', $this->linesOf($run, $assignment));
    }

    #[Test]
    public function overtime_uses_the_premium_multiplier_from_the_rule(): void
    {
        $period = $this->period($this->monthly, '2026-10-01', '2026-10-31');
        $assignment = $this->phEmployee($this->monthly, '26000.00');
        $this->timeInput($period, $assignment, 'overtime', '10');

        // hourly = 26000 x 12 / 260 / 8 = 150; 10h x 150 x 1.3
        $this->assertSame('1950.0000', $this->linesOf($this->calculate($this->runFor($period)), $assignment)['PREMIUM_OVERTIME']);
    }

    #[Test]
    public function a_rule_version_is_selected_by_the_date_its_applies_by_names(): void
    {
        // Fictional change: SSS bracket amounts double from 2026-10-01.
        StatutoryRuleVersion::where('rule_key', 'PH.SSS.CONTRIBUTION')->get()->each(fn ($r) => $r->forceFill(['effective_to' => '2026-09-30'])->save());
        $this->phRule('PH.SSS.CONTRIBUTION', 'bracket_lookup', [], [
            ['range_from' => '0', 'employee_amount' => '200', 'employer_amount' => '400', 'employer_extra_amount' => '10'],
            ['range_from' => '20000', 'employee_amount' => '600', 'employer_amount' => '1200', 'employer_extra_amount' => '30'],
        ], ['effective_from' => '2026-10-01', 'version' => 2]);

        // September work paid on 5 October.
        $period = $this->period($this->monthly, '2026-09-01', '2026-09-30', '2026-10-05');
        $assignment = $this->phEmployee($this->monthly);
        $this->assertSame('600.0000', $this->linesOf($this->calculate($this->runFor($period)), $assignment)['PH_SSS_EE']); // applies_by pay_date

        StatutoryRuleVersion::where('rule_key', 'PH.SSS.CONTRIBUTION')->get()->each(fn ($r) => $r->forceFill(['applies_by' => 'period_end'])->saveQuietly());
        $run = PayrollRun::first();
        app(PayrollRunService::class)->returnToDraft($run, $this->director, 'recalculate');
        $this->assertSame('300.0000', $this->linesOf($this->calculate($run->fresh()), $assignment)['PH_SSS_EE']); // applies_by period_end
    }

    #[Test]
    public function the_day_before_and_the_day_a_version_takes_effect_select_different_versions(): void
    {
        StatutoryRuleVersion::where('rule_key', 'PH.PHILHEALTH.PREMIUM')->get()->each(fn ($r) => $r->forceFill(['effective_to' => '2026-10-30'])->save());
        $this->phRule('PH.PHILHEALTH.PREMIUM', 'rate_with_floor_ceiling', ['rate' => '0.06', 'floor' => '8000', 'ceiling' => '60000', 'employee_share' => '0.5'], [], ['category' => 'healthcare', 'effective_from' => '2026-10-31', 'version' => 2]);
        $before = $this->period($this->monthly, '2026-09-01', '2026-09-30', '2026-10-30');
        $on = $this->period($this->monthly, '2026-10-01', '2026-10-31', '2026-10-31', 2);
        $a = $this->phEmployee($this->monthly);

        $this->assertSame('600.0000', $this->linesOf($this->calculate($this->runFor($before)), $a)['PH_PHIC_EE']);
        $this->assertSame('900.0000', $this->linesOf($this->calculate($this->runFor($on)), $a)['PH_PHIC_EE']);
    }

    #[Test]
    public function only_philippine_rule_versions_are_read(): void
    {
        StatutoryRuleVersion::where('rule_key', 'PH.SSS.CONTRIBUTION')->get()->each(fn ($r) => $r->forceFill(['verification_status' => 'superseded', 'effective_to' => '2025-12-31'])->save());
        // Same key, another country: must not be used for PH.
        $this->rule('PH.SSS.CONTRIBUTION', 'bracket_lookup', [], [['range_from' => '0', 'employee_amount' => '1', 'employer_amount' => '1']], ['country_code' => 'XX']);
        $period = $this->period($this->monthly, '2026-10-01', '2026-10-31');
        $this->phEmployee($this->monthly);

        $run = $this->calculate($this->runFor($period));

        $this->assertTrue($run->exceptions()->where('code', 'STATUTORY_RULE_MISSING')->where('message', 'like', '%PH.PH.SSS.CONTRIBUTION%')->exists());
    }

    #[Test]
    public function a_rate_below_the_regional_minimum_wage_rule_is_a_warning(): void
    {
        $this->phRule('PH.NWPC.MINIMUM_WAGE', 'parameters', ['daily_rate' => '700'], [], ['category' => 'minimum_wage', 'jurisdiction_code' => 'PH-00']);
        $period = $this->period($this->monthly, '2026-10-01', '2026-10-31');
        $this->phEmployee($this->monthly, '13000.00', assignment: ['work_subdivision_code' => 'PH-00']); // daily 600

        $run = $this->calculate($this->runFor($period));

        $this->assertTrue($run->exceptions()->where('code', 'PH_BELOW_MINIMUM_WAGE')->where('severity', 'warning')->exists());
        $this->assertFalse($run->hasBlockingExceptions());
    }
}
