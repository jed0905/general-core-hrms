<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\EmploymentAssignment;
use App\Models\LegalEntity;
use App\Models\PayGroup;
use App\Models\PayGroupCalendar;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollTimeInput;
use App\Models\StatutoryRuleVersion;
use App\Models\User;
use App\Services\Payroll\Localization\LocalizationRegistry;
use App\Services\Payroll\PayrollRunService;
use Illuminate\Support\Carbon;
use Tests\Feature\Leave\LeaveTestCase;
use Tests\Fixtures\Payroll\FictionalLocalization;

/**
 * Payroll fixture. Country "XX" is FICTIONAL (FictionalLocalization) and every
 * statutory value used with it is invented. Cast:
 * - $preparer and $reviewer hold the payroll role (prepare, calculate, review);
 * - $director holds hr_director (approve, finalize, verify rules).
 * Legal entity "XX Co" pays the monthly pay group "XX-M" in USD (2 minor units),
 * with a configured 8-hour day and 260 working days a year.
 * Time is frozen on 2026-10-10; the entity's timezone is explicit.
 *
 * Inherits LeaveTestCase's in-memory SQLite guard and seeded roles.
 */
abstract class PayrollTestCase extends LeaveTestCase
{
    protected User $preparer;

    protected User $reviewer;

    protected User $director;

    protected LegalEntity $entity;

    protected PayGroup $payGroup;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 09:00:00');

        app(LocalizationRegistry::class)->register(new FictionalLocalization);

        $this->preparer = $this->userWithRole('payroll');
        $this->reviewer = $this->userWithRole('payroll');
        $this->director = $this->userWithRole('hr_director');

        $this->entity = $this->legalEntity();
        $this->payGroup = $this->payGroup($this->entity);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function legalEntity(array $attributes = []): LegalEntity
    {
        return LegalEntity::create(array_merge([
            'code' => 'XXCO'.LegalEntity::count(),
            'name' => 'XX Co',
            'legal_name' => 'XX Company Ltd.',
            'country_code' => 'XX',
            'functional_currency' => 'USD',
            'timezone' => 'UTC',
            'locale' => 'en',
        ], $attributes));
    }

    protected function payGroup(LegalEntity $entity, array $attributes = []): PayGroup
    {
        return PayGroup::create(array_merge([
            'legal_entity_id' => $entity->id,
            'code' => 'XX-M'.PayGroup::count(),
            'name' => 'Monthly',
            'payroll_country_code' => 'XX',
            'pay_frequency' => 'monthly',
            'pay_currency' => 'USD',
            'hours_per_day' => '8',
            'annual_working_days' => '260',
            'proration_method' => 'calendar_days',
            'rounding_mode' => 'half_up',
        ], $attributes));
    }

    protected function period(PayGroup $payGroup, string $start, string $end, ?string $payDate = null, int $number = 1): PayrollPeriod
    {
        $calendar = PayGroupCalendar::firstOrCreate(
            ['pay_group_id' => $payGroup->id, 'calendar_year' => (int) substr($start, 0, 4)],
            ['name' => $payGroup->code.' '.substr($start, 0, 4)]
        );

        return PayrollPeriod::create([
            'payroll_calendar_id' => $calendar->id,
            'pay_group_id' => $payGroup->id,
            'period_number' => $number,
            'period_start' => $start,
            'period_end' => $end,
            'pay_date' => $payDate ?? $end,
            'tax_year' => (int) substr($payDate ?? $end, 0, 4),
            'status' => 'open',
        ]);
    }

    protected function employeeOn(PayGroup $payGroup, string $basis = 'monthly', string $amount = '3000.00', string $currency = 'USD', string $from = '2026-01-01', array $assignment = []): EmploymentAssignment
    {
        $employee = Employee::factory()->create();
        $assignment = EmploymentAssignment::create(array_merge([
            'employee_id' => $employee->id,
            'legal_entity_id' => $payGroup->legal_entity_id,
            'pay_group_id' => $payGroup->id,
            'work_country_code' => $payGroup->payroll_country_code,
            'payroll_country_code' => $payGroup->payroll_country_code,
            'effective_from' => $from,
        ], $assignment));

        $this->compensation($assignment, $basis, $amount, $currency, $from);

        return $assignment;
    }

    protected function compensation(EmploymentAssignment $assignment, string $basis, string $amount, string $currency = 'USD', string $from = '2026-01-01', ?string $to = null): EmployeeCompensation
    {
        return EmployeeCompensation::create([
            'employee_id' => $assignment->employee_id,
            'employment_assignment_id' => $assignment->id,
            'basis' => $basis,
            'amount' => $amount,
            'currency' => $currency,
            'effective_from' => $from,
            'effective_to' => $to,
            'status' => EmployeeCompensation::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    /**
     * A statutory rule version with fictional values.
     *
     * @param  array<string, string>  $parameters
     * @param  array<int, array<string, string|null>>  $brackets
     */
    protected function rule(string $key, string $method, array $parameters = [], array $brackets = [], array $attributes = []): StatutoryRuleVersion
    {
        // Values are attached while the version is a draft, then it is verified (verified versions are read-only).
        $status = $attributes['verification_status'] ?? 'verified';
        unset($attributes['verification_status']);

        $rule = StatutoryRuleVersion::create(array_merge([
            'country_code' => 'XX',
            'rule_key' => $key,
            'category' => 'social_insurance',
            'name' => $key.' (fictional)',
            'calculation_method' => $method,
            'applies_by' => 'pay_date',
            'effective_from' => '2026-01-01',
            'official_source' => 'Fictional authority (test fixture)',
            'source_url' => 'https://example.invalid/fictional',
            'issuance_reference' => 'Fictional circular 1',
            'retrieved_at' => '2026-01-01',
            'verification_status' => 'draft',
        ], $attributes));

        foreach ($parameters as $key => $value) {
            $rule->parameters()->create(['key' => $key, 'value' => $value]);
        }
        foreach (array_values($brackets) as $index => $bracket) {
            $rule->brackets()->create(['sequence' => $index + 1, ...$bracket]);
        }

        if ($status === 'verified') {
            $rule->forceFill(['verification_status' => 'verified', 'verified_at' => now(), 'verified_by' => $this->director->id])->save();
        }

        return $rule->fresh(['parameters', 'brackets']);
    }

    /** Fictional XX rules: 5% employee / 10% employer social contribution (floor 100, ceiling 5000), 10% tax above 1000/month, overtime x1.5. */
    protected function standardRules(array $attributes = []): void
    {
        $this->rule('XX.SOCIAL.CONTRIBUTION', 'rate_with_floor_ceiling', ['employee_rate' => '0.05', 'employer_rate' => '0.10', 'floor' => '100', 'ceiling' => '5000'], [], $attributes);
        foreach (['monthly', 'semi_monthly', 'biweekly', 'weekly'] as $frequency) {
            $this->rule('XX.TAX.WITHHOLDING', 'progressive_table', [], [
                ['range_from' => '0', 'fixed_amount' => '0', 'rate' => '0'],
                ['range_from' => '1000', 'fixed_amount' => '0', 'rate' => '0.10', 'excess_over' => '1000'],
            ], array_merge(['category' => 'income_tax', 'variant' => $frequency], $attributes));
        }
        $this->rule('XX.PREMIUM', 'multiplier', ['overtime' => '1.5'], [], array_merge(['category' => 'overtime'], $attributes));
    }

    protected function runFor(PayrollPeriod $period, string $type = 'regular'): PayrollRun
    {
        return app(PayrollRunService::class)->createRun(['payroll_period_id' => $period->id, 'run_type' => $type], $this->preparer);
    }

    protected function calculate(PayrollRun $run): PayrollRun
    {
        return app(PayrollRunService::class)->calculate($run, $this->preparer);
    }

    /** Calculate, review, approve and finalize with separate people. */
    protected function finalize(PayrollRun $run): PayrollRun
    {
        $service = app(PayrollRunService::class);
        if (in_array($run->status, ['draft', 'calculated'], true) && $run->run_type !== 'reversal') {
            $run = $service->calculate($run, $this->preparer);
        }
        $run = $service->review($run, $this->reviewer);
        $run = $service->approve($run, $this->director);

        return $service->finalize($run, $this->director);
    }

    protected function timeInput(PayrollPeriod $period, EmploymentAssignment $assignment, string $category, string $quantity, string $status = 'approved'): PayrollTimeInput
    {
        return PayrollTimeInput::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $assignment->employee_id,
            'category' => $category,
            'quantity' => $quantity,
            'status' => $status,
        ]);
    }

    /** @return array<string, string> code => amount for one employee's payslip in a run */
    protected function linesOf(PayrollRun $run, EmploymentAssignment $assignment): array
    {
        return $run->payslips()->where('employment_assignment_id', $assignment->id)->firstOrFail()
            ->lines->mapWithKeys(fn ($line) => [$line->code => (string) $line->amount])->all();
    }
}
