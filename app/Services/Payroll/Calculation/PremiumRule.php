<?php

namespace App\Services\Payroll\Calculation;

/**
 * Multiplier for one premium-time category, from a statutory rule version (or a
 * company rule the localization allows). The core pays hourly rate x hours x multiplier.
 */
final class PremiumRule
{
    public function __construct(
        public readonly string $category,
        public readonly string $label,
        public readonly string $multiplier,
        public readonly ?int $ruleVersionId,
    ) {}
}
