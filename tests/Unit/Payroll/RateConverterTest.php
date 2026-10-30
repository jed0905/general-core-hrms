<?php

namespace Tests\Unit\Payroll;

use App\Services\Payroll\Support\RateConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Rate conversion uses the configured working-time basis only. Values are
 * illustrative: an 8-hour day and 260 working days a year.
 */
class RateConverterTest extends TestCase
{
    private RateConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new RateConverter('8', '260');
    }

    #[Test]
    public function every_basis_annualizes_with_the_configured_divisors(): void
    {
        $this->assertSame('36000.0000000000', $this->converter->annualize('3000', 'monthly'));
        $this->assertSame('36000.0000000000', $this->converter->annualize('1500', 'semi_monthly'));
        $this->assertSame('39000.0000000000', $this->converter->annualize('1500', 'biweekly'));
        $this->assertSame('52000.0000000000', $this->converter->annualize('1000', 'weekly'));
        $this->assertSame('26000.0000000000', $this->converter->annualize('100', 'daily'));
        $this->assertSame('20800.0000000000', $this->converter->annualize('10', 'hourly'));
    }

    #[Test]
    public function period_amount_uses_the_amount_as_is_when_the_basis_matches_the_frequency(): void
    {
        $this->assertSame('3000', $this->converter->periodAmount('3000', 'monthly', 'monthly'));
        $this->assertSame('1500.0000000000', $this->converter->periodAmount('3000', 'monthly', 'semi_monthly'));
        $this->assertSame('1384.6153846153', $this->converter->periodAmount('3000', 'monthly', 'biweekly'));
        $this->assertSame('692.3076923076', $this->converter->periodAmount('3000', 'monthly', 'weekly'));
        $this->assertSame('3000.0000000000', $this->converter->periodAmount('36000', 'annual', 'monthly'));
    }

    #[Test]
    public function daily_and_hourly_rates_derive_from_the_working_time_basis(): void
    {
        $this->assertSame('138.4615384615', $this->converter->dailyRate('3000', 'monthly'));
        $this->assertSame('17.3076923076', $this->converter->hourlyRate('3000', 'monthly'));
        $this->assertSame('100', $this->converter->dailyRate('100', 'daily'));
        $this->assertSame('80.0000000000', $this->converter->dailyRate('10', 'hourly'));
        $this->assertSame('12.5000000000', $this->converter->hourlyRate('100', 'daily'));
    }

    #[Test]
    public function a_different_working_time_basis_changes_the_derived_rates(): void
    {
        $sixDayWeek = new RateConverter('8', '313');

        $this->assertSame('115.0159744408', $sixDayWeek->dailyRate('3000', 'monthly'));
    }
}
