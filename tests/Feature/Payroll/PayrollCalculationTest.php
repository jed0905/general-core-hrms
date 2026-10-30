<?php

namespace Tests\Feature\Payroll;

use App\Models\ExchangeRate;
use App\Models\PayrollRunException;
use App\Models\StatutoryRuleVersion;
use App\Services\Payroll\PayrollRunService;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Gross-to-net in the Global Payroll Core, using the FICTIONAL country XX
 * (5% employee / 10% employer contribution, 10% tax above 1000, overtime x1.5;
 * all invented). Invariants: gross = sum of earnings; net = gross - employee
 * deductions - tax; employer cost = gross + employer contributions; employer
 * contributions never reduce net. Amounts are asserted as exact strings.
 */
class PayrollCalculationTest extends PayrollTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->standardRules();
    }

    #[Test]
    public function a_monthly_salary_is_calculated_gross_to_net_with_separate_employer_contributions(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');

        $run = $this->calculate($this->runFor($period));
        $payslip = $run->payslips()->first();

        $this->assertSame([
            'BASIC' => '3000.0000',
            'XX_SOCIAL_EE' => '150.0000',
            'XX_SOCIAL_ER' => '300.0000',
            'XX_TAX' => '185.0000',
        ], $this->linesOf($run, $assignment));
        $this->assertSame('3000.0000', $payslip->gross_pay);
        $this->assertSame('150.0000', $payslip->employee_deductions);
        $this->assertSame('185.0000', $payslip->tax);
        $this->assertSame('2665.0000', $payslip->net_pay);
        $this->assertSame('300.0000', $payslip->employer_contributions);
        $this->assertSame('3300.0000', $payslip->employer_cost);
        $this->assertSame('2850.0000', $payslip->taxable_income);
        $this->assertSame('calculated', $run->status);
        $this->assertFalse($run->uses_unverified_rules);
    }

    #[Test]
    public function a_mid_period_hire_is_prorated_by_calendar_days(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00', from: '2026-10-16');

        $run = $this->calculate($this->runFor($period));

        // 16 of 31 days.
        $this->assertSame('1548.3900', $this->linesOf($run, $assignment)['BASIC']);
    }

    #[Test]
    public function a_salary_change_inside_the_period_pays_each_rate_for_its_own_days(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $assignment->compensations()->first()->update(['effective_to' => '2026-10-15']);
        $this->compensation($assignment, 'monthly', '3100.00', from: '2026-10-16');

        $run = $this->calculate($this->runFor($period));
        $basic = $run->lines()->where('code', 'BASIC')->orderBy('sequence')->pluck('amount')->all();

        $this->assertSame(['1451.6100', '1600.0000'], $basic);
        $this->assertSame('3051.6100', $run->payslips()->first()->gross_pay);
    }

    #[Test]
    public function compensation_effective_after_the_period_is_not_used(): void
    {
        $period = $this->period($this->payGroup, '2026-09-01', '2026-09-30');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $assignment->compensations()->first()->update(['effective_to' => '2026-09-30']);
        $this->compensation($assignment, 'monthly', '9999.00', from: '2026-10-01');

        $run = $this->calculate($this->runFor($period));

        $this->assertSame('3000.0000', $this->linesOf($run, $assignment)['BASIC']);
    }

    #[Test]
    public function absences_late_and_overtime_use_rates_derived_from_the_working_time_basis(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $this->timeInput($period, $assignment, 'absence_days', '2');
        $this->timeInput($period, $assignment, 'late_hours', '1.5');
        $this->timeInput($period, $assignment, 'overtime', '10');
        $this->timeInput($period, $assignment, 'undertime_hours', '4', status: 'pending'); // not approved: ignored

        $lines = $this->linesOf($this->calculate($this->runFor($period)), $assignment);

        // daily = 3000 x 12 / 260 = 138.4615...; hourly = daily / 8 = 17.3076...
        $this->assertSame('-276.9200', $lines['ABSENCE']);
        $this->assertSame('-25.9600', $lines['LATE']);
        $this->assertSame('259.6200', $lines['PREMIUM_OVERTIME']);
        $this->assertArrayNotHasKey('UNDERTIME', $lines);
    }

    #[Test]
    public function premium_time_without_a_rule_is_not_paid_and_blocks_the_run(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $this->timeInput($period, $assignment, 'night', '8'); // XX.PREMIUM has no "night" multiplier

        $run = $this->calculate($this->runFor($period));

        $this->assertArrayNotHasKey('PREMIUM_NIGHT', $this->linesOf($run, $assignment));
        $this->assertTrue($run->exceptions()->where('code', 'PREMIUM_RULE_MISSING')->exists());
        $this->assertTrue($run->hasBlockingExceptions());
    }

    #[Test]
    public function an_hourly_employee_is_paid_for_approved_hours_worked(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'hourly', '20.00');
        $this->timeInput($period, $assignment, 'hours_worked', '160');
        $this->timeInput($period, $assignment, 'absence_days', '3'); // time-based: absences are simply unpaid time

        $lines = $this->linesOf($this->calculate($this->runFor($period)), $assignment);

        $this->assertSame('3200.0000', $lines['BASIC']);
        $this->assertArrayNotHasKey('ABSENCE', $lines);
    }

    #[Test]
    public function each_pay_frequency_pays_its_share_of_a_monthly_salary(): void
    {
        $expected = ['semi_monthly' => '1500.0000', 'biweekly' => '1384.6200', 'weekly' => '692.3100'];

        foreach ($expected as $frequency => $basic) {
            $group = $this->payGroup($this->entity, ['pay_frequency' => $frequency]);
            $period = $this->period($group, '2026-10-01', '2026-10-14');
            $assignment = $this->employeeOn($group, 'monthly', '3000.00');

            $run = $this->calculate($this->runFor($period));

            $this->assertSame($basic, $this->linesOf($run, $assignment)['BASIC'], $frequency);
        }
    }

    #[Test]
    public function amounts_are_rounded_to_the_pay_currency_minor_units(): void
    {
        $yen = $this->payGroup($this->entity, ['pay_currency' => 'JPY']);
        $dinar = $this->payGroup($this->entity, ['pay_currency' => 'KWD']);
        $yenPeriod = $this->period($yen, '2026-10-01', '2026-10-31');
        $dinarPeriod = $this->period($dinar, '2026-10-01', '2026-10-31');
        $yenEmployee = $this->employeeOn($yen, 'monthly', '300000', 'JPY');
        $dinarEmployee = $this->employeeOn($dinar, 'monthly', '1000.000', 'KWD');
        $this->timeInput($yenPeriod, $yenEmployee, 'absence_days', '1');
        $this->timeInput($dinarPeriod, $dinarEmployee, 'absence_days', '1');

        $this->assertSame('-13846.0000', $this->linesOf($this->calculate($this->runFor($yenPeriod)), $yenEmployee)['ABSENCE']);
        $this->assertSame('-46.1540', $this->linesOf($this->calculate($this->runFor($dinarPeriod)), $dinarEmployee)['ABSENCE']);
    }

    #[Test]
    public function the_pay_group_rounding_mode_applies_to_non_statutory_lines(): void
    {
        $this->payGroup->update(['rounding_mode' => 'down']);
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00', from: '2026-10-16');

        $this->assertSame('1548.3800', $this->linesOf($this->calculate($this->runFor($period)), $assignment)['BASIC']);
    }

    #[Test]
    public function compensation_in_another_currency_is_converted_with_a_stored_rate(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00', 'EUR');
        ExchangeRate::create(['base_currency' => 'EUR', 'quote_currency' => 'USD', 'rate' => '1.10000000', 'rate_date' => '2026-10-30', 'source' => 'Test fixture']);
        ExchangeRate::create(['base_currency' => 'EUR', 'quote_currency' => 'USD', 'rate' => '9.00000000', 'rate_date' => '2026-11-02', 'source' => 'Test fixture']); // after period end

        $run = $this->calculate($this->runFor($period));
        $payslip = $run->payslips()->first();

        $this->assertSame('3300.0000', $this->linesOf($run, $assignment)['BASIC']);
        $this->assertSame('1.10000000', $payslip->snapshot['exchange_rates'][0]['rate']);
    }

    #[Test]
    public function a_missing_exchange_rate_is_an_error_not_a_guess(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup, 'monthly', '3000.00', 'EUR');

        $run = $this->calculate($this->runFor($period));

        $this->assertTrue($run->exceptions()->where('code', 'CALCULATION_FAILED')->where('message', 'like', '%EUR/USD%')->exists());
    }

    #[Test]
    public function a_missing_statutory_rule_stops_the_employee_and_blocks_review(): void
    {
        StatutoryRuleVersion::where('rule_key', 'XX.SOCIAL.CONTRIBUTION')->get()->each(fn ($r) => $r->forceFill(['verification_status' => 'superseded', 'effective_to' => '2026-06-30'])->save());
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup);

        $run = $this->calculate($this->runFor($period));

        $this->assertTrue($run->exceptions()->where('code', 'STATUTORY_RULE_MISSING')->exists());
        $this->expectException(ValidationException::class);
        app(PayrollRunService::class)->review($run, $this->reviewer);
    }

    #[Test]
    public function a_country_without_a_localization_cannot_be_paid_statutorily(): void
    {
        $group = $this->payGroup($this->entity, ['payroll_country_code' => 'YY']);
        $period = $this->period($group, '2026-10-01', '2026-10-31');
        $this->employeeOn($group);

        $run = $this->calculate($this->runFor($period));

        $this->assertTrue($run->exceptions()->where('code', 'NO_LOCALIZATION')->where('severity', PayrollRunException::SEVERITY_ERROR)->exists());
    }

    #[Test]
    public function an_employee_without_approved_compensation_is_flagged(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup);
        $assignment->compensations()->update(['status' => 'pending']);

        $run = $this->calculate($this->runFor($period));

        $this->assertTrue($run->exceptions()->where('code', 'NO_COMPENSATION')->exists());
    }

    #[Test]
    public function negative_net_pay_is_an_error(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $this->timeInput($period, $assignment, 'absence_days', '25');

        $run = $this->calculate($this->runFor($period));

        $this->assertTrue($run->exceptions()->where('code', 'NEGATIVE_NET_PAY')->exists());
    }

    #[Test]
    public function many_employees_are_calculated_and_totalled_per_run(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $this->employeeOn($this->payGroup, 'monthly', '2000.00');
        $this->employeeOn($this->payGroup, 'monthly', '800.00');
        $otherGroup = $this->payGroup($this->entity);
        $this->employeeOn($otherGroup, 'monthly', '5000.00'); // other pay group: not in this run

        $run = $this->calculate($this->runFor($period));

        // 3000: net 2665. 2000: 100 ee, (1900-1000)x10% = 90 tax, net 1810. 800: 40 ee, no tax, net 760.
        $this->assertSame(3, $run->employee_count);
        $this->assertSame('5800.0000', $run->gross_total);
        $this->assertSame('5235.0000', $run->net_total);
        $this->assertSame('580.0000', $run->employer_contribution_total);
        $this->assertSame('6380.0000', $run->employer_cost_total);
    }
}
