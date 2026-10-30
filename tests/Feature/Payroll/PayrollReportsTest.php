<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollRun;
use App\Services\Payroll\PayrollRunService;
use App\Services\Reports\ReportRegistry;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;

/**
 * Payroll reports read finalized stored results only, per currency, within the
 * viewer's legal-entity scope. Statutory values are FICTIONAL (country XX).
 */
class PayrollReportsTest extends PayrollTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->standardRules();

        $october = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $this->employeeOn($this->payGroup, 'monthly', '2000.00');
        $this->finalize($this->runFor($october));

        $november = $this->period($this->payGroup, '2026-11-01', '2026-11-30', number: 2);
        $this->calculate($this->runFor($november)); // not finalized: excluded from reports
    }

    private function report(string $key, array $query = [])
    {
        return $this->actingAs($this->director)->get(route("reports.{$key}.show", ['date_from' => '2026-01-01', 'date_to' => '2026-12-31', ...$query]));
    }

    #[Test]
    public function every_payroll_report_renders_for_a_permitted_user(): void
    {
        foreach (ReportRegistry::REPORTS as $class) {
            if ($class::category() === 'payroll') {
                $this->report($class::key())->assertOk();
            }
        }
    }

    #[Test]
    public function the_summary_and_register_show_finalized_totals_only(): void
    {
        $this->report('payroll-summary')->assertInertia(fn (AssertableInertia $p) => $p
            ->has('rows.data', 1)
            ->where('rows.data.0.gross', '5000.00')
            ->where('rows.data.0.net', '4475.00')
            ->where('rows.data.0.employer_cost', '5500.00')
            ->where('summary.0.rows.0', ['USD', 1, '5000.00', '4475.00', '5500.00']));

        $this->report('payroll-register')->assertInertia(fn (AssertableInertia $p) => $p->has('rows.data', 2));
    }

    #[Test]
    public function contributions_show_employee_and_employer_shares_separately(): void
    {
        $this->report('payroll-statutory-contributions')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('summary.0.rows.0', ['XX.SOCIAL.CONTRIBUTION', 'USD', '250.00', '500.00']));
    }

    #[Test]
    public function the_reconciliation_balances_a_finalized_run_and_its_reversal(): void
    {
        $run = PayrollRun::where('status', 'finalized')->first();
        $this->finalize(app(PayrollRunService::class)->reverse($run, $this->preparer, 'test'));

        $this->report('payroll-reconciliation')->assertInertia(fn (AssertableInertia $p) => $p
            ->has('rows.data', 2)
            ->where('rows.data.0.result', 'Balanced')
            ->where('rows.data.1.result', 'Balanced')
            ->where('rows.data.1.run_net', '-4475.00'));
        $this->report('payroll-summary')->assertInertia(fn (AssertableInertia $p) => $p->where('summary.0.rows.0', ['USD', 2, '0.00', '0.00', '0.00']));
    }

    #[Test]
    public function reports_are_limited_to_the_viewers_legal_entities(): void
    {
        $scoped = $this->userWithRole('hr_director');
        $scoped->roles()->first()->revokePermissionTo('payroll.scope.all');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($scoped->fresh())->get(route('reports.payroll-summary.show', ['date_from' => '2026-01-01', 'date_to' => '2026-12-31']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('rows.data', 0));
    }

    #[Test]
    public function users_without_the_payroll_report_permission_are_refused(): void
    {
        $this->actingAs($this->userWithRole('hr_manager'))->get(route('reports.payroll-summary.show'))->assertForbidden();
        $this->actingAs($this->userWithRole('employee'))->get(route('reports.payroll-register.show'))->assertForbidden();
    }

    #[Test]
    public function the_register_exports_to_excel(): void
    {
        $this->actingAs($this->director)->get(route('reports.payroll-register.excel', ['date_from' => '2026-01-01', 'date_to' => '2026-12-31']))->assertOk();
    }
}
