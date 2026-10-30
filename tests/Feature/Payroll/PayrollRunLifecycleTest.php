<?php

namespace Tests\Feature\Payroll;

use App\Models\EmployeeBankAccount;
use App\Models\EmployeeCompensation;
use App\Models\PayComponent;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\StatutoryRuleVersion;
use App\Services\Payroll\PayrollRunService;
use App\Services\Payroll\Support\Decimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Run lifecycle: draft -> calculated -> reviewed -> approved -> finalized, with
 * separation of duties, immutability of finalized runs, one active regular run
 * per period, reversals and supplemental runs, and historical rule versions.
 * Statutory values are FICTIONAL (country XX).
 */
class PayrollRunLifecycleTest extends PayrollTestCase
{
    private PayrollRunService $runs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->standardRules();
        $this->runs = app(PayrollRunService::class);
    }

    #[Test]
    public function a_run_moves_through_review_approval_and_finalization_with_an_audit_trail(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup);

        $run = $this->finalize($this->runFor($period));

        $this->assertSame('finalized', $run->status);
        $this->assertSame(['created', 'calculated', 'reviewed', 'approved', 'finalized'], $run->events()->pluck('event')->all());
        $this->assertSame($this->preparer->id, $run->calculated_by);
        $this->assertSame($this->reviewer->id, $run->reviewed_by);
        $this->assertSame($this->director->id, $run->approved_by);
        $this->assertMatchesRegularExpression('/^PS-2026-\d{6}$/', $run->payslips()->first()->payslip_number);
        $this->assertMatchesRegularExpression('/^PR-2026-\d{5}$/', $run->run_number);
    }

    #[Test]
    public function the_calculator_cannot_review_approve_or_finalize_their_own_run(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup);
        $run = $this->calculate($this->runFor($period));

        $this->assertThrows(fn () => $this->runs->review($run, $this->preparer), ValidationException::class);

        $run = $this->runs->review($run, $this->reviewer);
        $this->assertThrows(fn () => $this->runs->approve($run, $this->reviewer), ValidationException::class);
        $this->assertThrows(fn () => $this->runs->approve($run, $this->preparer), ValidationException::class);

        $run = $this->runs->approve($run, $this->director);
        $this->assertThrows(fn () => $this->runs->finalize($run, $this->preparer), ValidationException::class);
        $this->assertSame('approved', $run->fresh()->status);
    }

    #[Test]
    public function steps_cannot_be_skipped(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup);
        $run = $this->runFor($period);

        $this->assertThrows(fn () => $this->runs->review($run, $this->reviewer), ValidationException::class);
        $run = $this->calculate($run);
        $this->assertThrows(fn () => $this->runs->approve($run, $this->director), ValidationException::class);
        $this->assertThrows(fn () => $this->runs->finalize($run, $this->director), ValidationException::class);
    }

    #[Test]
    public function only_one_active_regular_run_may_exist_per_period(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $run = $this->runFor($period);

        $this->assertThrows(fn () => $this->runFor($period), ValidationException::class);

        $this->runs->cancel($run, $this->preparer, 'Created by mistake');
        $this->assertSame('draft', $this->runFor($period)->status);
        $this->assertSame(1, PayrollRun::where('payroll_period_id', $period->id)->where('status', '<>', 'cancelled')->count());
    }

    #[Test]
    public function the_database_rejects_a_second_active_regular_run_even_without_the_service_check(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $run = $this->runFor($period);

        $this->expectException(QueryException::class);
        PayrollRun::create([...$run->only(['legal_entity_id', 'pay_group_id', 'payroll_period_id', 'currency']), 'run_number' => 'DUP-1', 'run_type' => 'regular', 'status' => 'draft']);
    }

    #[Test]
    public function finalized_runs_payslips_and_lines_cannot_be_modified_or_deleted(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup);
        $run = $this->finalize($this->runFor($period));
        $payslip = $run->payslips()->first();
        $line = $run->lines()->first();

        $this->assertThrows(fn () => $run->update(['description' => 'edited']), LogicException::class);
        $this->assertThrows(fn () => $run->delete(), LogicException::class);
        $this->assertThrows(fn () => $payslip->update(['net_pay' => '1.0000']), LogicException::class);
        $this->assertThrows(fn () => $line->update(['amount' => '1.0000']), LogicException::class);
        $this->assertThrows(fn () => $line->delete(), LogicException::class);
        $this->assertThrows(fn () => $this->runs->calculate($run, $this->preparer), ValidationException::class);
        $this->assertThrows(fn () => $this->runs->cancel($run, $this->preparer, 'no'), ValidationException::class);
        $this->assertSame('3000.0000', $run->lines()->where('code', 'BASIC')->value('amount'));
    }

    #[Test]
    public function compensation_used_by_a_finalized_run_cannot_be_rewritten(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup);
        $this->finalize($this->runFor($period));
        $compensation = $assignment->compensations()->first();

        $this->assertThrows(fn () => $compensation->update(['amount' => '9999.00']), LogicException::class);
        $compensation = $compensation->fresh();
        $compensation->update(['effective_to' => '2026-11-30']); // ending it is allowed
        $this->assertSame('2026-11-30', $compensation->fresh()->effective_to->toDateString());
        $this->assertThrows(fn () => $compensation->delete(), LogicException::class);
    }

    #[Test]
    public function a_run_using_unverified_rules_can_be_previewed_but_not_finalized(): void
    {
        StatutoryRuleVersion::where('rule_key', 'XX.SOCIAL.CONTRIBUTION')->get()->each(fn ($r) => $r->forceFill(['verification_status' => 'superseded', 'effective_to' => '2025-12-31'])->save());
        $this->rule('XX.SOCIAL.CONTRIBUTION', 'rate_with_floor_ceiling', ['employee_rate' => '0.05', 'employer_rate' => '0.10'], [], ['verification_status' => 'draft', 'effective_from' => '2026-01-01']);
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup);

        $run = $this->calculate($this->runFor($period));
        $this->assertTrue($run->uses_unverified_rules);
        $this->assertTrue($run->exceptions()->where('code', 'UNVERIFIED_RULES')->exists());

        $run = $this->runs->approve($this->runs->review($run, $this->reviewer), $this->director);
        $this->assertThrows(fn () => $this->runs->finalize($run, $this->director), ValidationException::class);
        $this->assertSame('approved', $run->fresh()->status);
    }

    #[Test]
    public function each_period_uses_the_rule_version_in_force_and_history_is_not_recalculated(): void
    {
        // Fictional rate change: 5% until September, 6% from October.
        StatutoryRuleVersion::where('rule_key', 'XX.SOCIAL.CONTRIBUTION')->get()->each(fn ($r) => $r->forceFill(['effective_to' => '2026-09-30'])->save());
        $september = $this->period($this->payGroup, '2026-09-01', '2026-09-30');
        $october = $this->period($this->payGroup, '2026-10-01', '2026-10-31', number: 2);
        $assignment = $this->employeeOn($this->payGroup);

        $septemberRun = $this->finalize($this->runFor($september));
        $this->rule('XX.SOCIAL.CONTRIBUTION', 'rate_with_floor_ceiling', ['employee_rate' => '0.06', 'employer_rate' => '0.10', 'floor' => '100', 'ceiling' => '5000'], [], ['effective_from' => '2026-10-01', 'version' => 2]);
        $octoberRun = $this->calculate($this->runFor($october));

        $this->assertSame('150.0000', $this->linesOf($septemberRun->fresh(), $assignment)['XX_SOCIAL_EE']);
        $this->assertSame('180.0000', $this->linesOf($octoberRun, $assignment)['XX_SOCIAL_EE']);
        $this->assertNotSame(
            $septemberRun->lines()->where('code', 'XX_SOCIAL_EE')->value('statutory_rule_version_id'),
            $octoberRun->lines()->where('code', 'XX_SOCIAL_EE')->value('statutory_rule_version_id')
        );
    }

    #[Test]
    public function the_payslip_snapshot_records_what_the_calculation_used(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup);
        $run = $this->finalize($this->runFor($period));
        $snapshot = $run->payslips()->first()->snapshot;

        $this->assertSame(config('payroll.engine_version'), $snapshot['engine_version']);
        $this->assertSame('XX', $snapshot['localization']);
        $this->assertSame([$assignment->compensations()->first()->id], $snapshot['compensation_ids']);
        $this->assertSame('3000.0000', $snapshot['compensations'][0]['amount']);
        $this->assertSame('260.0000', $snapshot['pay_group']['annual_working_days']);
        $this->assertCount(2, $snapshot['rule_versions']); // social contribution + monthly tax table (no premium hours, so no premium rule)
    }

    #[Test]
    public function a_reversal_negates_every_line_and_needs_its_own_approval(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup);
        $original = $this->finalize($this->runFor($period));

        $reversal = $this->runs->reverse($original, $this->preparer, 'Wrong salary');
        $this->assertSame('calculated', $reversal->status);
        $this->assertSame('-2665.0000', $reversal->net_total);
        $this->assertSame('-3300.0000', $reversal->employer_cost_total);
        $this->assertSame(
            $original->lines()->orderBy('id')->pluck('id')->all(),
            $reversal->lines()->orderBy('id')->pluck('reverses_line_id')->all()
        );
        $this->assertThrows(fn () => $this->runs->reverse($original, $this->preparer, 'again'), ValidationException::class);
        $this->assertThrows(fn () => $this->runs->calculate($reversal, $this->preparer), ValidationException::class);

        $reversal = $this->finalize($reversal);
        $this->assertSame('finalized', $reversal->status);
        $this->assertSame('finalized', $original->fresh()->status); // the original is never edited
        $this->assertSame('0.0000', Decimal::format(Decimal::add((string) $original->net_total, (string) $reversal->net_total), 4));
    }

    #[Test]
    public function a_supplemental_run_pays_only_approved_adjustments_and_marks_them_applied(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup);
        $this->employeeOn($this->payGroup); // no adjustment: not in the supplemental run
        $this->finalize($this->runFor($period));

        $bonus = PayComponent::create(['code' => 'BONUS', 'name' => 'Bonus', 'line_class' => 'earning', 'category' => 'bonus']);
        $adjustment = PayrollAdjustment::create(['payroll_period_id' => $period->id, 'employee_id' => $assignment->employee_id, 'pay_component_id' => $bonus->id, 'amount' => '500.00', 'currency' => 'USD', 'status' => 'approved']);
        PayrollAdjustment::create(['payroll_period_id' => $period->id, 'employee_id' => $assignment->employee_id, 'pay_component_id' => $bonus->id, 'amount' => '999.00', 'currency' => 'USD', 'status' => 'pending']);

        $supplemental = $this->finalize($this->runFor($period, 'supplemental'));

        $this->assertSame(1, $supplemental->employee_count);
        $lines = $this->linesOf($supplemental, $assignment);
        $this->assertSame('500.0000', $lines['BONUS']);
        $this->assertArrayNotHasKey('BASIC', $lines);
        $this->assertArrayNotHasKey('XX_SOCIAL_EE', $lines); // the fictional localization levies on regular runs only
        $this->assertSame('applied', $adjustment->fresh()->status);
        $this->assertSame($supplemental->id, $adjustment->fresh()->applied_run_id);
        $this->assertThrows(fn () => $adjustment->fresh()->update(['amount' => '1.00']), LogicException::class);
    }

    #[Test]
    public function an_excluded_employee_is_not_paid_and_stays_excluded_on_recalculation(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup);
        $excluded = $this->employeeOn($this->payGroup, 'monthly', '2000.00');
        $run = $this->calculate($this->runFor($period));

        $this->runs->excludeEmployee($run, $excluded->id, 'Final pay handled separately', $this->preparer);
        $run = $this->calculate($run->fresh());

        $this->assertSame(1, $run->employee_count);
        $this->assertSame('3000.0000', $run->gross_total);
        $this->assertSame('excluded', $run->payslips()->where('employment_assignment_id', $excluded->id)->value('status'));
        $this->assertSame(0, PayrollRunLine::whereHas('payslip', fn ($q) => $q->where('employment_assignment_id', $excluded->id))->count());

        $this->runs->includeEmployee($run, $excluded->id, $this->preparer);
        $this->assertSame('draft', $run->fresh()->status);
        $this->assertSame(2, $this->calculate($run->fresh())->employee_count);
    }

    #[Test]
    public function a_returned_run_must_be_recalculated_before_review(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup);
        $run = $this->runs->review($this->calculate($this->runFor($period)), $this->reviewer);

        $run = $this->runs->returnToDraft($run, $this->director, 'Overtime missing');

        $this->assertSame('draft', $run->status);
        $this->assertNull($run->reviewed_by);
        $this->assertThrows(fn () => $this->runs->review($run, $this->reviewer), ValidationException::class);
    }

    #[Test]
    public function finalization_opens_a_disbursement_per_positive_payslip(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup);
        EmployeeBankAccount::create(['employee_id' => $assignment->employee_id, 'bank_name' => 'Test Bank', 'bank_identifier' => 'TESTXX22', 'account_name' => 'Test Person', 'account_number' => '0001234567', 'account_last4' => '4567', 'currency' => 'USD']);

        $run = $this->finalize($this->runFor($period));
        $disbursement = $run->disbursements()->first();

        $this->assertSame('2665.0000', $disbursement->amount);
        $this->assertSame('bank_transfer', $disbursement->method);
        $this->assertSame('pending', $disbursement->status);
        $this->assertSame('0001234567', $disbursement->destination['account_number']);
        $this->assertStringNotContainsString('0001234567', (string) DB::table('payroll_disbursements')->value('destination')); // encrypted at rest
        $this->assertSame(EmployeeCompensation::STATUS_APPROVED, $assignment->compensations()->first()->status);
    }
}
