<?php

namespace App\Services\Payroll\Calculation;

use App\Models\PayrollRunLine;

/**
 * A payslip line before rounding. amount is an unrounded decimal string in the
 * pay currency; the calculator rounds it once (roundingPlaces / roundingMode
 * override the currency precision / pay-group mode when a statutory rule says so).
 * Deduction, tax and employer amounts are positive; earnings may be negative
 * (absences, leave without pay).
 */
final class PayLine
{
    /**
     * @param  array<string, mixed>  $inputs  values the amount was computed from (stored for reproducibility)
     */
    public function __construct(
        public readonly string $lineClass,
        public readonly string $code,
        public readonly string $description,
        public string $amount,
        public readonly ?string $quantity = null,
        public readonly ?string $rate = null,
        public readonly ?string $multiplier = null,
        public ?int $payComponentId = null,
        public ?int $ruleVersionId = null,
        public ?int $adjustmentId = null,
        public ?string $taxTreatment = null,
        public ?string $contributionTreatment = null,
        public array $inputs = [],
        public ?string $roundingMode = null,
        public ?int $roundingPlaces = null,
    ) {}

    public function isEarning(): bool
    {
        return $this->lineClass === PayrollRunLine::CLASS_EARNING;
    }
}
