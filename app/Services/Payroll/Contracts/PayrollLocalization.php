<?php

namespace App\Services\Payroll\Contracts;

use App\Services\Payroll\Calculation\ComponentTreatment;
use App\Services\Payroll\Calculation\PayLine;
use App\Services\Payroll\Calculation\PayrollContext;
use App\Services\Payroll\Calculation\PremiumRuleSet;

/**
 * One country's payroll law. The Global Payroll Core calls these hooks and never
 * branches on a country. Statutory values come only from statutory rule versions
 * (resolved through $context->rules()); a localization never hard-codes them.
 */
interface PayrollLocalization
{
    /** ISO 3166-1 alpha-2 code; the registry key. */
    public function countryCode(): string;

    public function name(): string;

    /** @return array<string, string> tax treatment key => label */
    public function taxTreatments(): array;

    /** @return array<string, string> contribution treatment key => label */
    public function contributionTreatments(): array;

    /** @return array<string, string> premium time-input category => label */
    public function premiumCategories(): array;

    /**
     * Fields of the employee statutory profile for this country.
     *
     * @return array<string, array{label: string, rules: array<int, mixed>, sensitive?: bool, options?: array<string, string>}>
     */
    public function statutoryProfileFields(): array;

    /**
     * Rule keys the localization reads, with their category and calculation method (for the rules screen).
     *
     * @return array<string, array{category: string, methods: array<int, string>, description: string, variants?: array<int, string>}>
     */
    public function ruleCatalog(): array;

    /**
     * Stamp tax/contribution treatment onto the lines and compute statutory bases.
     *
     * @param  array<int, PayLine>  $lines
     */
    public function classify(PayrollContext $context, array $lines): ComponentTreatment;

    /**
     * Statutory employee deductions and employer contributions (separate lines).
     *
     * @return array<int, PayLine>
     */
    public function statutoryLines(PayrollContext $context, ComponentTreatment $treatment): array;

    /**
     * Income tax withholding lines.
     *
     * @param  array<int, PayLine>  $statutoryLines
     * @return array<int, PayLine>
     */
    public function taxLines(PayrollContext $context, ComponentTreatment $treatment, array $statutoryLines): array;

    public function premiumRules(PayrollContext $context): PremiumRuleSet;

    /** @return array<int, string> content a payslip must show in this country (for the payslip template) */
    public function payslipRequirements(): array;
}
