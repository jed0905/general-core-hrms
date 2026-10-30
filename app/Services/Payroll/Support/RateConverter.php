<?php

namespace App\Services\Payroll\Support;

use InvalidArgumentException;

/**
 * Converts a compensation amount between bases using the pay group's
 * working-time basis (hours per day, annual working days). No divisor is
 * assumed: both come from configuration.
 */
class RateConverter
{
    /** Periods per year for the periodic salary bases. */
    public const PERIODIC = [
        'annual' => '1',
        'monthly' => '12',
        'semi_monthly' => '24',
        'biweekly' => '26',
        'weekly' => '52',
    ];

    public function __construct(
        private readonly string $hoursPerDay,
        private readonly string $annualWorkingDays,
    ) {}

    public function annualize(string $amount, string $basis): string
    {
        return match (true) {
            isset(self::PERIODIC[$basis]) => Decimal::mul($amount, self::PERIODIC[$basis]),
            $basis === 'daily' => Decimal::mul($amount, $this->annualWorkingDays),
            $basis === 'hourly' => Decimal::mul(Decimal::mul($amount, $this->hoursPerDay), $this->annualWorkingDays),
            default => throw new InvalidArgumentException("Unknown compensation basis {$basis}."),
        };
    }

    /**
     * Amount per pay period for a periodic salary. When the basis equals the pay
     * frequency the amount is used as is (no annualize/divide drift).
     */
    public function periodAmount(string $amount, string $basis, string $payFrequency): string
    {
        if ($basis === $payFrequency) {
            return Decimal::of($amount);
        }

        if (! isset(self::PERIODIC[$payFrequency])) {
            throw new InvalidArgumentException("Unknown pay frequency {$payFrequency}.");
        }

        return Decimal::div($this->annualize($amount, $basis), self::PERIODIC[$payFrequency]);
    }

    public function dailyRate(string $amount, string $basis): string
    {
        return match ($basis) {
            'daily' => Decimal::of($amount),
            'hourly' => Decimal::mul($amount, $this->hoursPerDay),
            default => Decimal::div($this->annualize($amount, $basis), $this->annualWorkingDays),
        };
    }

    public function hourlyRate(string $amount, string $basis): string
    {
        return $basis === 'hourly'
            ? Decimal::of($amount)
            : Decimal::div($this->dailyRate($amount, $basis), $this->hoursPerDay);
    }

    /** Monthly equivalent (annualized / 12), e.g. for monthly statutory bases. */
    public function monthly(string $amount, string $basis): string
    {
        return $basis === 'monthly' ? Decimal::of($amount) : Decimal::div($this->annualize($amount, $basis), '12');
    }
}
