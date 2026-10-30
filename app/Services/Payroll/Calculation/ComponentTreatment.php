<?php

namespace App\Services\Payroll\Calculation;

/**
 * What a localization's classify() returns: named statutory bases (decimal
 * strings, keys defined by the localization) and the taxable income shown on
 * the payslip. The localization also stamps tax/contribution treatment keys
 * onto the lines it classified.
 */
final class ComponentTreatment
{
    /**
     * @param  array<string, string>  $bases
     */
    public function __construct(
        public array $bases = [],
        public string $taxableIncome = '0',
    ) {}

    public function base(string $key): string
    {
        return $this->bases[$key] ?? '0';
    }
}
