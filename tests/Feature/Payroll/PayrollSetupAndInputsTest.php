<?php

namespace Tests\Feature\Payroll;

use App\Models\EmployeeBankAccount;
use App\Models\EmployeeStatutoryProfile;
use App\Models\PayComponent;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollTimeInput;
use App\Models\StatutoryRuleVersion;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;

/**
 * Configuration and inputs over HTTP: calendar generation per frequency,
 * effective-dated compensation, two-person approval of inputs, closed periods,
 * payment tracking and the ISO 20022 file. Statutory values are FICTIONAL (XX).
 */
class PayrollSetupAndInputsTest extends PayrollTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->standardRules();
    }

    #[Test]
    public function every_payroll_page_renders_for_a_superadmin(): void
    {
        $admin = $this->userWithRole('superadmin');
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup);
        $run = $this->finalize($this->runFor($period));

        $pages = [
            [route('payroll.dashboard'), 'app/Payroll/Dashboard/Index'],
            [route('payroll.setup.index', ['tab' => 'groups', 'pay_group_id' => $this->payGroup->id]), 'app/Payroll/Setup/Index'],
            [route('payroll.employees.index'), 'app/Payroll/Employees/Index'],
            [route('payroll.employees.show', $assignment->employee_id), 'app/Payroll/Employees/Show'],
            [route('payroll.statutory-rules.index'), 'app/Payroll/StatutoryRules/Index'],
            [route('payroll.inputs.index', ['payroll_period_id' => $period->id]), 'app/Payroll/Inputs/Index'],
            [route('payroll.runs.index'), 'app/Payroll/Runs/Index'],
            [route('payroll.runs.show', $run), 'app/Payroll/Runs/Show'],
            [route('payroll.payslips.show', $run->payslips()->first()), 'app/Payroll/Payslips/Show'],
            [route('payroll.my-payslips.index'), 'app/Payroll/MyPayslips/Index'],
        ];

        foreach ($pages as [$url, $component]) {
            $this->assertFileExists(resource_path("js/pages/{$component}.vue"));
            $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component($component, false));
        }
    }

    #[Test]
    public function calendars_are_generated_for_each_frequency_without_overlaps(): void
    {
        $semi = $this->payGroup($this->entity, ['pay_frequency' => 'semi_monthly']);
        $biweekly = $this->payGroup($this->entity, ['pay_frequency' => 'biweekly']);

        $this->actingAs($this->preparer)->post(route('payroll.setup.pay-groups.calendars.store', $this->payGroup), ['calendar_year' => 2027, 'pay_date_offset_days' => 5])->assertSessionHasNoErrors();
        $this->actingAs($this->preparer)->post(route('payroll.setup.pay-groups.calendars.store', $semi), ['calendar_year' => 2027, 'pay_date_offset_days' => 0])->assertSessionHasNoErrors();
        $this->actingAs($this->preparer)->post(route('payroll.setup.pay-groups.calendars.store', $biweekly), ['calendar_year' => 2027, 'pay_date_offset_days' => 3])->assertSessionHasErrors('first_period_start');
        $this->actingAs($this->preparer)->post(route('payroll.setup.pay-groups.calendars.store', $biweekly), ['calendar_year' => 2027, 'pay_date_offset_days' => 3, 'first_period_start' => '2027-01-04'])->assertSessionHasNoErrors();
        $this->actingAs($this->preparer)->post(route('payroll.setup.pay-groups.calendars.store', $this->payGroup), ['calendar_year' => 2027, 'pay_date_offset_days' => 0])->assertSessionHasErrors('calendar_year');

        $monthly = PayrollPeriod::where('pay_group_id', $this->payGroup->id)->orderBy('period_start')->get();
        $this->assertCount(12, $monthly);
        $this->assertSame(['2027-02-01', '2027-02-28', '2027-03-05'], [$monthly[1]->period_start->toDateString(), $monthly[1]->period_end->toDateString(), $monthly[1]->pay_date->toDateString()]);
        $this->assertSame(2028, $monthly[11]->tax_year); // paid 2028-01-05

        $this->assertCount(24, PayrollPeriod::where('pay_group_id', $semi->id)->get());
        $this->assertSame('2027-02-16', PayrollPeriod::where('pay_group_id', $semi->id)->where('period_number', 4)->first()->period_start->toDateString());
        $periods = PayrollPeriod::where('pay_group_id', $biweekly->id)->orderBy('period_start')->get();
        $this->assertCount(26, $periods);
        $this->assertSame('2027-01-17', $periods[0]->period_end->toDateString());
    }

    #[Test]
    public function a_salary_change_cannot_be_backdated_before_a_later_record(): void
    {
        $assignment = $this->employeeOn($this->payGroup);
        $this->actingAs($this->preparer)->post(route('payroll.compensations.store', $assignment), ['basis' => 'monthly', 'amount' => '3200.00', 'currency' => 'USD', 'effective_from' => '2026-12-01', 'reason' => 'Raise'])->assertSessionHasNoErrors();
        $this->actingAs($this->director)->put(route('payroll.compensations.approve', $assignment->compensations()->where('status', 'pending')->first()));

        $this->actingAs($this->preparer)->post(route('payroll.compensations.store', $assignment), ['basis' => 'monthly', 'amount' => '3100.00', 'currency' => 'USD', 'effective_from' => '2026-11-01', 'reason' => 'Backdated'])->assertSessionHasErrors('effective_from');
        $this->assertSame(['2026-01-01', '2026-12-01'], $assignment->compensations()->orderBy('effective_from')->get()->map(fn ($c) => $c->effective_from->toDateString())->all());
    }

    #[Test]
    public function discarding_a_pending_salary_change_reopens_the_previous_record(): void
    {
        $assignment = $this->employeeOn($this->payGroup);
        $this->actingAs($this->preparer)->post(route('payroll.compensations.store', $assignment), ['basis' => 'monthly', 'amount' => '3200.00', 'currency' => 'USD', 'effective_from' => '2026-12-01', 'reason' => 'Raise']);
        $pending = $assignment->compensations()->where('status', 'pending')->first();

        $this->actingAs($this->preparer)->delete(route('payroll.compensations.destroy', $pending))->assertSessionHasNoErrors();

        $this->assertNull($assignment->compensations()->sole()->effective_to);
    }

    #[Test]
    public function inputs_are_paid_only_after_someone_else_approves_them(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup);
        $bonus = PayComponent::create(['code' => 'BONUS', 'name' => 'Bonus', 'line_class' => 'earning', 'category' => 'bonus']);

        $this->actingAs($this->preparer)->post(route('payroll.inputs.time.store', $period), ['employee_id' => $assignment->employee_id, 'category' => 'overtime', 'quantity' => '4'])->assertSessionHasNoErrors();
        $this->actingAs($this->preparer)->post(route('payroll.inputs.time.store', $period), ['employee_id' => $assignment->employee_id, 'category' => 'teleporting', 'quantity' => '4'])->assertSessionHasErrors('category');
        $this->actingAs($this->preparer)->post(route('payroll.inputs.adjustments.store', $period), ['employee_id' => $assignment->employee_id, 'pay_component_id' => $bonus->id, 'amount' => '100.00', 'currency' => 'USD', 'remarks' => 'Spot award'])->assertSessionHasNoErrors();
        $adjustment = PayrollAdjustment::sole();

        $this->actingAs($this->preparer)->put(route('payroll.inputs.adjustments.approve', $adjustment))->assertSessionHasErrors('adjustment');
        $this->actingAs($this->reviewer)->put(route('payroll.inputs.adjustments.approve', $adjustment))->assertSessionHasNoErrors();
        $this->actingAs($this->reviewer)->put(route('payroll.inputs.time.approve', $period), ['ids' => [PayrollTimeInput::sole()->id]])->assertSessionHasNoErrors();

        $lines = $this->linesOf($this->calculate($this->runFor($period)), $assignment);
        $this->assertSame('100.0000', $lines['BONUS']);
        $this->assertSame('103.8500', $lines['PREMIUM_OVERTIME']); // 4h x 17.3077 x 1.5
    }

    #[Test]
    public function time_inputs_can_be_imported_from_csv(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup);
        $number = $assignment->employee->employee_number;
        $csv = "employee_number,category,quantity\n{$number},absence_days,1\nNOPE,absence_days,1\n{$number},overtime,abc\n";

        $this->actingAs($this->preparer)->post(route('payroll.inputs.time.import', $period), ['file' => UploadedFile::fake()->createWithContent('time.csv', $csv)])
            ->assertSessionHas('success', '1 time inputs imported (pending approval).')
            ->assertSessionHas('error');
        $this->assertSame('pending', PayrollTimeInput::sole()->status);
    }

    #[Test]
    public function a_closed_period_accepts_no_new_run_or_inputs(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup);
        $this->actingAs($this->preparer)->put(route('payroll.setup.periods.status', $period), ['status' => 'closed'])->assertSessionHasNoErrors();

        $this->actingAs($this->preparer)->post(route('payroll.runs.store'), ['payroll_period_id' => $period->id, 'run_type' => 'regular'])->assertSessionHasErrors('payroll_period_id');
        $this->actingAs($this->preparer)->post(route('payroll.inputs.time.store', $period), ['employee_id' => $assignment->employee_id, 'category' => 'absence_days', 'quantity' => '1'])->assertSessionHasErrors('period');
    }

    #[Test]
    public function a_period_with_an_open_run_cannot_be_closed(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $this->runFor($period);

        $this->actingAs($this->preparer)->put(route('payroll.setup.periods.status', $period), ['status' => 'closed'])->assertSessionHasErrors('status');
    }

    #[Test]
    public function payments_are_tracked_and_exported_as_iso_20022(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup);
        EmployeeBankAccount::create(['employee_id' => $assignment->employee_id, 'bank_name' => 'Test Bank', 'bank_identifier' => 'TESTXX22', 'account_name' => 'Pat Example', 'account_number' => '0001234567', 'account_last4' => '4567', 'iban' => 'XX00TEST0001234567', 'currency' => 'USD']);
        $run = $this->finalize($this->runFor($period));
        $disbursement = $run->disbursements()->sole();

        $response = $this->actingAs($this->preparer)->post(route('payroll.runs.disbursements.pain001', $run), ['iban' => 'XX99EMPLOYER0001', 'bic' => 'EMPLXX22']);
        $response->assertOk();
        $xml = $response->streamedContent();
        $this->assertStringContainsString('urn:iso:std:iso:20022:tech:xsd:pain.001.001.03', $xml);
        $this->assertStringContainsString('<CtrlSum>2665.00</CtrlSum>', $xml);
        $this->assertStringContainsString('<InstdAmt Ccy="USD">2665.00</InstdAmt>', $xml);
        $this->assertStringContainsString('<IBAN>XX00TEST0001234567</IBAN>', $xml);
        $this->assertStringContainsString('<Cd>SALA</Cd>', $xml);
        $this->assertSame('exported', $disbursement->fresh()->status);

        $this->actingAs($this->preparer)->put(route('payroll.runs.disbursements.update', [$run, $disbursement]), ['status' => 'paid', 'reference' => 'BANK-REF-1'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $disbursement->fresh()->status);
        $this->actingAs($this->preparer)->put(route('payroll.runs.disbursements.update', [$run, $disbursement]), ['status' => 'pending'])->assertSessionHasErrors('status');
        $this->actingAs($this->userWithRole('hr_director'))->get(route('payroll.runs.disbursements.csv', $run))->assertForbidden(); // no payroll.disburse
    }

    #[Test]
    public function overlapping_verified_rule_versions_are_refused(): void
    {
        $closed = $this->rule('XX.PREMIUM', 'multiplier', ['overtime' => '1.5'], [], ['category' => 'overtime', 'effective_from' => '2027-01-01', 'effective_to' => '2027-06-30', 'verification_status' => 'verified', 'version' => 2]);
        $this->actingAs($this->preparer)->post(route('payroll.statutory-rules.store'), [
            'country_code' => 'XX', 'rule_key' => 'XX.PREMIUM', 'category' => 'overtime', 'name' => 'Overlapping', 'calculation_method' => 'multiplier',
            'applies_by' => 'period_end', 'effective_from' => '2027-03-01', 'effective_to' => '2027-12-31', 'official_source' => 'Fictional', 'source_url' => 'https://example.invalid/x',
            'issuance_reference' => 'Fictional 3', 'retrieved_at' => '2026-10-10', 'parameters' => [['key' => 'overtime', 'value' => '2']],
        ])->assertSessionHasNoErrors();
        $draft = StatutoryRuleVersion::where('name', 'Overlapping')->sole();

        $this->actingAs($this->director)->put(route('payroll.statutory-rules.verify', $draft))->assertSessionHasErrors('rule');
        $this->assertSame('draft', $draft->fresh()->verification_status);
        $this->assertSame('2027-06-30', $closed->fresh()->effective_to->toDateString());
    }

    #[Test]
    public function statutory_profile_fields_are_validated_by_the_country_localization(): void
    {
        $assignment = $this->employeeOn($this->payGroup);
        $url = route('payroll.employees.statutory-profiles.store', $assignment->employee_id);

        $this->actingAs($this->preparer)->post($url, ['country_code' => 'XX', 'effective_from' => '2026-01-01', 'fields' => ['tax_code' => str_repeat('A', 30)]])->assertSessionHasErrors('tax_code');
        $this->actingAs($this->preparer)->post($url, ['country_code' => 'YY', 'effective_from' => '2026-01-01', 'fields' => []])->assertSessionHasErrors('country_code');
        $this->actingAs($this->preparer)->post($url, ['country_code' => 'XX', 'effective_from' => '2026-01-01', 'fields' => ['tax_code' => 'B2', 'social_number' => 'SN-1']])->assertSessionHasNoErrors();
        $this->actingAs($this->preparer)->post($url, ['country_code' => 'XX', 'effective_from' => '2026-06-01', 'fields' => ['tax_code' => 'C3']])->assertSessionHasNoErrors();

        $profiles = EmployeeStatutoryProfile::where('employee_id', $assignment->employee_id)->orderBy('effective_from')->get();
        $this->assertSame('2026-05-31', $profiles[0]->effective_to->toDateString());
        $this->assertSame(['tax_code' => 'C3'], $profiles[1]->fields);
    }

    #[Test]
    public function only_users_with_access_to_every_entity_assign_payroll_users_to_an_entity(): void
    {
        $clerk = $this->userWithRole('payroll');
        $url = route('payroll.setup.legal-entities.users', $this->entity);

        $this->actingAs($this->preparer)->put($url, ['user_ids' => [$clerk->id]])->assertSessionHasNoErrors();
        $this->assertSame([$clerk->id], $this->entity->payrollUsers()->pluck('users.id')->all());

        $scoped = $this->userWithRole('hr_staff');
        $scoped->givePermissionTo('payroll.config.manage');
        $this->actingAs($scoped)->put($url, ['user_ids' => [$scoped->id]])->assertForbidden();
    }
}
