<?php

namespace Tests\Feature\Payroll;

use App\Models\EmployeeBankAccount;
use App\Models\EmployeeStatutoryProfile;
use App\Models\PayrollRun;
use App\Models\StatutoryRuleVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;

/**
 * HTTP authorization for payroll: permission matrix per seeded role,
 * legal-entity scope, separation of duties, and employee payslip privacy
 * (own payslips only, finalized runs only). Statutory values are FICTIONAL (XX).
 */
class PayrollAccessTest extends PayrollTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->standardRules();
    }

    private function finalizedRunFor(User $owner): PayrollRun
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup);
        $assignment->update(['employee_id' => $owner->employee_id]);
        $assignment->compensations()->update(['employee_id' => $owner->employee_id]);

        return $this->finalize($this->runFor($period));
    }

    #[Test]
    public function payroll_pages_follow_the_permission_matrix(): void
    {
        $matrix = [
            // route => roles allowed (others must get 403)
            'payroll.dashboard' => ['payroll', 'hr_director', 'superadmin'],
            'payroll.runs.index' => ['payroll', 'hr_director', 'superadmin'],
            'payroll.setup.index' => ['payroll', 'hr_director', 'superadmin'],
            'payroll.statutory-rules.index' => ['payroll', 'hr_director', 'superadmin'],
            'payroll.employees.index' => ['payroll', 'hr_director', 'hr_manager', 'superadmin'],
            'payroll.inputs.index' => ['payroll', 'superadmin'],
            'payroll.my-payslips.index' => ['payroll', 'hr_director', 'hr_manager', 'hr_staff', 'supervisor', 'employee', 'superadmin'],
        ];
        $roles = ['superadmin', 'hr_director', 'hr_manager', 'hr_staff', 'payroll', 'supervisor', 'employee'];
        $users = collect($roles)->mapWithKeys(fn ($role) => [$role => $this->userWithRole($role)]);

        foreach ($matrix as $route => $allowed) {
            foreach ($roles as $role) {
                $response = $this->actingAs($users[$role])->get(route($route));
                in_array($role, $allowed, true)
                    ? $response->assertOk()
                    : $response->assertForbidden();
            }
        }
    }

    #[Test]
    public function an_employee_sees_only_their_own_finalized_payslips(): void
    {
        $alice = $this->userWithRole('employee');
        $bob = $this->userWithRole('employee');
        $run = $this->finalizedRunFor($alice);
        $payslip = $run->payslips()->first();

        $this->actingAs($alice)->get(route('payroll.my-payslips.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('app/Payroll/MyPayslips/Index', false)->has('payslips.data', 1)->where('payslips.data.0.net_pay', '2665.00'));
        $this->actingAs($alice)->get(route('payroll.payslips.show', $payslip))->assertOk();
        $this->actingAs($alice)->get(route('payroll.payslips.pdf', $payslip))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($bob)->get(route('payroll.payslips.show', $payslip))->assertForbidden();
        $this->actingAs($bob)->get(route('payroll.payslips.pdf', $payslip))->assertForbidden();
        $this->actingAs($bob)->get(route('payroll.my-payslips.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('payslips.data', 0));
        $this->actingAs($alice)->get(route('payroll.runs.show', $run))->assertForbidden();
    }

    #[Test]
    public function an_unfinalized_payslip_is_hidden_from_its_employee(): void
    {
        $alice = $this->userWithRole('employee');
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup);
        $assignment->update(['employee_id' => $alice->employee_id]);
        $assignment->compensations()->update(['employee_id' => $alice->employee_id]);
        $run = $this->calculate($this->runFor($period));

        $this->actingAs($alice)->get(route('payroll.payslips.show', $run->payslips()->first()))->assertForbidden();
        $this->actingAs($alice)->get(route('payroll.my-payslips.index'))->assertInertia(fn (AssertableInertia $p) => $p->has('payslips.data', 0));
    }

    #[Test]
    public function payroll_staff_only_reach_the_legal_entities_assigned_to_them(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup);
        $run = $this->calculate($this->runFor($period));

        $otherEntity = $this->legalEntity(['code' => 'OTHER']);
        $scoped = $this->userWithRole('payroll');
        $scoped->revokePermissionTo('payroll.scope.all');
        $scoped->roles()->first()->revokePermissionTo('payroll.scope.all');
        $scoped->payrollEntities()->attach($otherEntity->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $scoped = $scoped->fresh();

        $this->actingAs($scoped)->get(route('payroll.runs.show', $run))->assertForbidden();
        $this->actingAs($scoped)->put(route('payroll.runs.calculate', $run))->assertForbidden();
        $this->actingAs($scoped)->get(route('payroll.runs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('runs.data', 0));

        $scoped->payrollEntities()->attach($this->entity->id);
        $this->actingAs($scoped->fresh())->get(route('payroll.runs.show', $run))->assertOk();
    }

    #[Test]
    public function separation_of_duties_is_enforced_over_http(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->employeeOn($this->payGroup);
        $run = $this->runFor($period);

        $this->actingAs($this->preparer)->put(route('payroll.runs.calculate', $run))->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($this->preparer)->put(route('payroll.runs.review', $run))->assertForbidden();
        $this->actingAs($this->reviewer)->put(route('payroll.runs.review', $run))->assertRedirect();
        $this->actingAs($this->reviewer)->put(route('payroll.runs.approve', $run))->assertForbidden(); // payroll role can't approve
        $this->actingAs($this->director)->put(route('payroll.runs.approve', $run))->assertRedirect();
        $this->actingAs($this->preparer)->put(route('payroll.runs.finalize', $run))->assertForbidden();
        $this->actingAs($this->director)->put(route('payroll.runs.finalize', $run))->assertRedirect();

        $this->assertSame('finalized', $run->fresh()->status);
        $this->actingAs($this->director)->post(route('payroll.runs.reverse', $run), [])->assertSessionHasErrors('remarks');
        $this->actingAs($this->preparer)->post(route('payroll.runs.reverse', $run), ['remarks' => 'x'])->assertForbidden(); // payroll role has no reverse
    }

    #[Test]
    public function a_statutory_rule_cannot_be_verified_by_the_person_who_entered_it(): void
    {
        $payload = [
            'country_code' => 'XX', 'rule_key' => 'XX.PREMIUM', 'category' => 'overtime', 'name' => 'Fictional premium v2',
            'calculation_method' => 'multiplier', 'applies_by' => 'period_end', 'effective_from' => '2027-01-01',
            'official_source' => 'Fictional authority', 'source_url' => 'https://example.invalid/premium', 'issuance_reference' => 'Fictional 2',
            'retrieved_at' => '2026-10-10', 'parameters' => [['key' => 'overtime', 'value' => '1.75']],
        ];

        $this->actingAs($this->preparer)->post(route('payroll.statutory-rules.store'), $payload)->assertSessionHasNoErrors();
        $rule = StatutoryRuleVersion::where('effective_from', '2027-01-01')->firstOrFail();
        $this->assertSame('draft', $rule->verification_status);
        $this->assertSame(2, $rule->version);

        $director = $this->userWithRole('hr_director');
        $director->givePermissionTo('payroll.statutory_rule.manage');
        $this->actingAs($director)->post(route('payroll.statutory-rules.store'), [...$payload, 'effective_from' => '2028-01-01'])->assertSessionHasNoErrors();
        $own = StatutoryRuleVersion::where('effective_from', '2028-01-01')->firstOrFail();
        $this->actingAs($director)->put(route('payroll.statutory-rules.verify', $own))->assertForbidden();

        $this->actingAs($this->preparer)->put(route('payroll.statutory-rules.verify', $rule))->assertForbidden(); // no verify permission
        $this->actingAs($this->director)->put(route('payroll.statutory-rules.verify', $rule), ['notes' => 'Checked against fictional circular'])->assertSessionHasNoErrors();

        $rule->refresh();
        $this->assertSame('verified', $rule->verification_status);
        // The open-ended v1 is closed the day before and kept as superseded history.
        $previous = StatutoryRuleVersion::where('rule_key', 'XX.PREMIUM')->where('version', 1)->first();
        $this->assertSame('superseded', $previous->verification_status);
        $this->assertSame('2026-12-31', $previous->effective_to->toDateString());
        $this->assertThrows(fn () => $rule->parameters()->first()->update(['value' => '9']), LogicException::class);
    }

    #[Test]
    public function a_rule_key_outside_the_country_catalog_is_rejected(): void
    {
        $this->actingAs($this->preparer)->post(route('payroll.statutory-rules.store'), [
            'country_code' => 'XX', 'rule_key' => 'XX.INVENTED', 'category' => 'overtime', 'name' => 'x', 'calculation_method' => 'multiplier',
            'applies_by' => 'pay_date', 'effective_from' => '2027-01-01', 'official_source' => 'x', 'source_url' => 'https://example.invalid',
            'issuance_reference' => 'x', 'retrieved_at' => '2026-10-10',
        ])->assertSessionHasErrors('rule_key');
    }

    #[Test]
    public function compensation_changes_need_approval_by_someone_else(): void
    {
        $assignment = $this->employeeOn($this->payGroup);
        $hrManager = $this->userWithRole('hr_manager');
        $hrManager->payrollEntities()->attach($this->entity->id);

        $this->actingAs($hrManager)->post(route('payroll.compensations.store', $assignment), [
            'basis' => 'monthly', 'amount' => '3500.00', 'currency' => 'USD', 'effective_from' => '2026-11-01', 'reason' => 'Promotion',
        ])->assertSessionHasNoErrors();
        $pending = $assignment->compensations()->where('status', 'pending')->firstOrFail();
        $this->assertSame('2026-10-31', $assignment->compensations()->where('status', 'approved')->first()->effective_to->toDateString());

        $this->actingAs($this->preparer)->put(route('payroll.compensations.approve', $pending))->assertForbidden();
        $this->actingAs($this->director)->put(route('payroll.compensations.approve', $pending))->assertSessionHasNoErrors();
        $this->assertSame('approved', $pending->fresh()->status);
    }

    #[Test]
    public function amounts_with_more_decimals_than_the_currency_allows_are_rejected(): void
    {
        $assignment = $this->employeeOn($this->payGroup);

        $this->actingAs($this->preparer)->post(route('payroll.compensations.store', $assignment), [
            'basis' => 'monthly', 'amount' => '3500.005', 'currency' => 'USD', 'effective_from' => '2026-11-01', 'reason' => 'x',
        ])->assertSessionHasErrors('amount');
        $this->actingAs($this->preparer)->post(route('payroll.compensations.store', $assignment), [
            'basis' => 'monthly', 'amount' => '350000.50', 'currency' => 'JPY', 'effective_from' => '2026-11-01', 'reason' => 'x',
        ])->assertSessionHasErrors('amount');
    }

    #[Test]
    public function statutory_ids_and_bank_numbers_are_never_sent_in_full(): void
    {
        $assignment = $this->employeeOn($this->payGroup);
        EmployeeStatutoryProfile::create(['employee_id' => $assignment->employee_id, 'country_code' => 'XX', 'fields' => ['social_number' => 'SN-998877', 'tax_code' => 'A1'], 'effective_from' => '2026-01-01']);
        EmployeeBankAccount::create(['employee_id' => $assignment->employee_id, 'bank_name' => 'Test Bank', 'account_name' => 'Test', 'account_number' => '000111222333', 'account_last4' => '2333', 'currency' => 'USD']);

        $response = $this->actingAs($this->preparer)->get(route('payroll.employees.show', $assignment->employee_id))->assertOk();

        $this->assertStringNotContainsString('SN-998877', $response->getContent());
        $this->assertStringNotContainsString('000111222333', $response->getContent());
        $this->assertStringContainsString('8877', $response->getContent());
        $this->assertStringNotContainsString('000111222333', (string) DB::table('employee_bank_accounts')->value('account_number'));
    }

    #[Test]
    public function the_employee_page_hides_salaries_without_compensation_view(): void
    {
        $assignment = $this->employeeOn($this->payGroup);
        $viewer = $this->userWithRole('hr_staff');
        $viewer->givePermissionTo(['payroll.config.view', 'payroll.scope.all']);

        $this->actingAs($viewer)->get(route('payroll.employees.show', $assignment->employee_id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('compensations', null)->where('payComponents', null));
    }
}
