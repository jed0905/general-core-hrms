<?php

namespace Tests\Feature\Reports;

use App\Exports\ReportExport;
use App\Models\Employee;
use App\Models\EmployeeMovementType;
use App\Models\EmploymentStatus;
use App\Models\Holiday;
use App\Models\User;
use App\Services\EmployeeMovementService;
use App\Services\Reports\ReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Testing\AssertableInertia;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;

class CoreHrReportsTest extends ReportsTestCase
{
    public static function reports(): array
    {
        return collect(ReportRegistry::REPORTS)
            ->mapWithKeys(fn ($class) => [$class::key() => [$class::key(), $class::permission()]])
            ->all();
    }

    // ---------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------

    #[Test]
    public function hr_users_can_open_the_employee_reports_and_catalog(): void
    {
        $staff = $this->reportUser('hr_staff');

        $this->actingAs($staff)->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('app/Reports/Index', false)
                ->where('categories', fn ($categories) => collect($categories)->pluck('key')->all() === ['employee', 'work_schedule', 'attendance', 'leave']));

        foreach (collect(ReportRegistry::REPORTS)->filter(fn ($c) => $c::category() === 'employee') as $class) {
            $this->actingAs($staff)->get($this->showUrl($class::key()))->assertOk();
        }
    }

    #[Test]
    #[DataProvider('reports')]
    public function plain_employees_get_403_for_every_organization_wide_report(string $key): void
    {
        $employee = $this->userWithRole('employee');

        foreach (['show', 'excel', 'pdf', 'print'] as $format) {
            $this->actingAs($employee)->get(route("reports.{$key}.{$format}"))->assertForbidden();
        }

        $this->actingAs($employee)->get(route('reports.index'))->assertForbidden();
    }

    #[Test]
    public function self_service_permissions_never_grant_report_access(): void
    {
        $selfService = Role::create(['name' => 'all_self_service', 'guard_name' => 'web'])->givePermissionTo([
            'employee.view_own', 'employee.update_own', 'work_schedule.view_own', 'attendance.view_own', 'attendance.export_own',
            'leave.view_own', 'leave.create', 'leave.view_balance_own', 'leave.view_history_own', 'employee_movement.view_own', 'dashboard.view',
        ]);
        $user = User::factory()->create(['employee_id' => $this->ana->id])->assignRole($selfService);

        foreach (self::reportKeys() as $key) {
            $this->actingAs($user)->get($this->showUrl($key))->assertForbidden();
            $this->actingAs($user)->get(route("reports.{$key}.excel"))->assertForbidden();
        }
    }

    #[Test]
    public function each_category_needs_its_own_permission(): void
    {
        // Leave reports only: payroll.
        $payroll = $this->reportUser('payroll');
        $this->actingAs($payroll)->get($this->showUrl('leave-balance'))->assertOk();
        foreach (['employee-masterlist', 'attendance-log', 'daily-roster', 'user-accounts'] as $key) {
            $this->actingAs($payroll)->get($this->showUrl($key))->assertForbidden();
        }

        // User & access: HR Director yes, HR Manager no.
        $this->actingAs($this->reportUser('hr_director'))->get($this->showUrl('user-accounts'))->assertOk();
        $this->actingAs($this->reportUser('hr_manager'))->get($this->showUrl('user-accounts'))->assertForbidden();
    }

    #[Test]
    public function exports_need_report_export_as_well_as_the_category(): void
    {
        // Supervisors hold report.attendance but not report.export.
        $supervisor = $this->userWithRole('supervisor');
        $this->actingAs($supervisor)->get($this->showUrl('attendance-log'))->assertOk();

        foreach (['excel', 'pdf', 'print'] as $format) {
            $this->actingAs($supervisor)->get(route("reports.attendance-log.{$format}"))->assertForbidden();
        }

        $staff = $this->reportUser('hr_staff');
        Role::findByName('hr_staff')->revokePermissionTo('report.export');
        $this->actingAs($staff->fresh())->get(route('reports.employee-masterlist.excel'))->assertForbidden();
        $this->actingAs($staff->fresh())->get($this->showUrl('employee-masterlist'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canExport', false));
    }

    // ---------------------------------------------------------------
    // Filters and exports use the same dataset
    // ---------------------------------------------------------------

    #[Test]
    public function filters_change_the_results(): void
    {
        $hr = $this->reportUser('hr_director');
        $names = fn (array $rows) => collect($rows)->pluck('name')->sort()->values()->all();

        // default: active only (Dan is archived)
        $this->assertNotContains('Lim, Dan', $names($this->rowsOf($hr, 'employee-masterlist')));

        // department with and without sub-departments
        $this->assertSame(['Cruz, Ben', 'Reyes, Ana'], $names($this->rowsOf($hr, 'employee-masterlist', ['department_id' => $this->it->id])));
        $this->assertSame(['Reyes, Ana'], $names($this->rowsOf($hr, 'employee-masterlist', ['department_id' => $this->it->id, 'include_sub_departments' => 0])));

        // other attributes
        $this->assertSame(['Cruz, Ben'], $names($this->rowsOf($hr, 'employee-masterlist', ['employment_status_id' => $this->probationary->id])));
        $this->assertSame(['Diaz, Cara', 'Reyes, Ana'], $names($this->rowsOf($hr, 'employee-masterlist', ['sex' => 'female'])));
        $this->assertSame(['Cruz, Ben'], $names($this->rowsOf($hr, 'employee-masterlist', ['supervisor_id' => $this->ana->id])));
        $this->assertSame(['Lim, Dan'], $names($this->rowsOf($hr, 'employee-masterlist', ['status' => 'archived'])));

        // sorting is whitelisted
        $rows = $this->rowsOf($hr, 'employee-masterlist', ['sort' => 'name', 'direction' => 'desc']);
        $this->assertSame('Reyes, Ana', $rows[0]['name']);
        $this->actingAs($hr)->get($this->showUrl('employee-masterlist', ['sort' => 'password']))->assertSessionHasErrors('sort');
    }

    #[Test]
    public function excel_export_uses_the_same_filters(): void
    {
        Excel::fake();
        $hr = $this->reportUser('hr_director');

        $this->actingAs($hr)->get(route('reports.employee-masterlist.excel', ['department_id' => $this->hr->id]))->assertOk();

        Excel::assertDownloaded('employee-masterlist_20261007_090000.xlsx', function (ReportExport $export) {
            $rows = iterator_to_array($export->report->rows($export->filters), false);

            return count($rows) === 1
                && $rows[0]['name'] === 'Diaz, Cara'
                && count($export->sheets()) === 2; // data + info (masterlist has no summary)
        });
    }

    #[Test]
    public function pdf_and_print_use_the_same_filters(): void
    {
        $hr = $this->reportUser('hr_director');

        // Print is the PDF's HTML.
        $this->actingAs($hr)->get(route('reports.employee-masterlist.print', ['department_id' => $this->hr->id]))
            ->assertOk()
            ->assertSee('Diaz, Cara')
            ->assertDontSee('Reyes, Ana')
            ->assertSee('Department: Human Resources')
            ->assertSee('window.print()', false);

        // The PDF gets the same document.
        $captured = null;
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('%PDF-fake', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($view, $data) use (&$captured, $pdf) {
            $captured = $data;

            return $pdf;
        });

        $this->actingAs($hr)->get(route('reports.employee-masterlist.pdf', ['department_id' => $this->hr->id]))->assertOk();
        $this->assertSame(['Diaz, Cara'], collect($captured['rows'])->pluck('name')->all());
        $this->assertSame(['Department' => 'Human Resources', 'Include sub-departments' => 'Yes', 'Record Status' => 'Active'], $captured['filters']);
        $this->assertSame('Employee Masterlist', $captured['title']);
    }

    #[Test]
    public function a_real_pdf_is_generated(): void
    {
        $response = $this->actingAs($this->reportUser('hr_director'))->get(route('reports.headcount.pdf'));

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    // ---------------------------------------------------------------
    // Employee movement report
    // ---------------------------------------------------------------

    #[Test]
    public function movement_report_shows_history_and_filters_by_type(): void
    {
        $recorder = User::factory()->create()->assignRole('hr_director');
        $movements = app(EmployeeMovementService::class);
        $type = fn ($code) => EmployeeMovementType::where('code', $code)->value('id');

        $movements->createMovement(['employee_id' => $this->ben->id, 'movement_type_id' => $type('promotion'), 'effective_date' => '2026-09-01', 'reason' => 'Great work', 'changed_fields' => ['employment_status_id'], 'to_employment_status_id' => $this->regular->id], $recorder);
        $movements->createMovement(['employee_id' => $this->cara->id, 'movement_type_id' => $type('transfer'), 'effective_date' => '2026-09-15', 'changed_fields' => ['department_id'], 'to_department_id' => $this->it->id], $recorder);
        $cancelled = $movements->createMovement(['employee_id' => $this->ana->id, 'movement_type_id' => $type('transfer'), 'effective_date' => '2026-09-20', 'changed_fields' => ['department_id'], 'to_department_id' => $this->hr->id], $recorder);
        $movements->cancelMovement($cancelled, $recorder, 'Mistake');

        $hr = $this->reportUser('hr_manager');

        // default: effective movements only
        $rows = collect($this->rowsOf($hr, 'employee-movements'));
        $this->assertSame(['Diaz, Cara', 'Cruz, Ben'], $rows->pluck('employee')->all());

        $transfer = $rows->firstWhere('employee', 'Diaz, Cara');
        $this->assertSame(['Human Resources', 'Information Technology'], [$transfer['from_department'], $transfer['to_department']]);

        // one report, filtered per movement type ("Promotion report")
        $promotions = $this->rowsOf($hr, 'employee-movements', ['movement_type_id' => $type('promotion')]);
        $this->assertCount(1, $promotions);
        $this->assertSame(['Probationary', 'Regular', 'Great work'], [$promotions[0]['from_employment_status'], $promotions[0]['to_employment_status'], $promotions[0]['reason']]);

        // cancelled only on request
        $this->assertSame(['Reyes, Ana'], collect($this->rowsOf($hr, 'employee-movements', ['status' => 'cancelled']))->pluck('employee')->all());

        // summary by type
        $this->actingAs($hr)->get($this->showUrl('employee-movements'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('summary.0.rows', [['Promotion', 1, 50], ['Transfer', 1, 50]]));
    }

    #[Test]
    public function movement_data_is_not_exposed_without_report_employee(): void
    {
        $recorder = User::factory()->create()->assignRole('hr_director');
        app(EmployeeMovementService::class)->createMovement([
            'employee_id' => $this->ben->id,
            'movement_type_id' => EmployeeMovementType::where('code', 'promotion')->value('id'),
            'effective_date' => '2026-09-01',
            'changed_fields' => ['job_title_id'],
            'to_job_title_id' => $this->recruiter->id,
        ], $recorder);

        // Ben himself (employee_movement.view_own) and a supervisor (org-wide report.leave/attendance) get nothing here.
        $ben = User::factory()->create(['employee_id' => $this->ben->id])->assignRole('employee');
        foreach ([$ben, $this->userWithRole('supervisor'), $this->reportUser('payroll')] as $user) {
            $this->actingAs($user)->get($this->showUrl('employee-movements'))->assertForbidden();
            $this->actingAs($user)->get(route('reports.employee-movements.excel'))->assertForbidden();
        }
    }

    // ---------------------------------------------------------------
    // Specific reports
    // ---------------------------------------------------------------

    #[Test]
    public function demographics_and_headcount_are_aggregates_without_names(): void
    {
        $hr = $this->reportUser('hr_director');

        $response = $this->actingAs($hr)->get($this->showUrl('employee-demographics'))->assertOk();
        $props = $response->viewData('page')['props'];
        $sections = collect($props['summary'])->keyBy('title');

        $this->assertNull($props['rows']);
        $this->assertSame([['Female', 2, 66.7], ['Male', 1, 33.3]], $sections['By Sex']['rows']);
        // Ana 30, Ben 41, Cara unknown (as of 2026-10-07)
        $this->assertSame([['30–39', 1, 33.3], ['40–49', 1, 33.3], ['Not specified', 1, 33.3]], $sections['By Age Group']['rows']);
        foreach (['Reyes', 'Cruz', 'Diaz', 'Ana'] as $name) {
            $this->assertStringNotContainsString($name, json_encode($props));
        }

        $headcount = collect($this->actingAs($hr)->get($this->showUrl('headcount'))->viewData('page')['props']['summary'])->keyBy('title');
        $this->assertSame([['Active', 3, 75.0], ['Archived', 1, 25.0]], $headcount['By Record Status']['rows']);
        $this->assertSame([['Regular', 2, 66.7], ['Probationary', 1, 33.3]], $headcount['By Employment Status']['rows']);
    }

    #[Test]
    public function employment_status_and_organization_reports_use_configured_data(): void
    {
        $hr = $this->reportUser('hr_director');
        EmployeeMovementType::query(); // no-op; statuses come from employment_statuses
        EmploymentStatus::create(['name' => 'Contractual']);
        Employee::factory()->create(['emp_first_name' => 'Eve', 'emp_last_name' => 'Tan', 'employment_status_id' => null, 'department_id' => null]);

        $status = $this->actingAs($hr)->get($this->showUrl('employment-status'))->viewData('page')['props']['summary'][0]['rows'];
        $this->assertSame([['Contractual', 0, 0.0], ['Probationary', 1, 25.0], ['Regular', 2, 50.0], ['Not specified', 1, 25.0]], $status);

        $departments = collect($this->actingAs($hr)->get($this->showUrl('department-organization'))->viewData('page')['props']['summary'][0]['rows'])->keyBy(0);
        $this->assertSame(['Information Technology', 1, 2], $departments['Information Technology']);
        $this->assertSame(['Information Technology › Development', 1, 1], $departments['Information Technology › Development']);
        $this->assertSame(['No department', 1, 1], $departments['No department']);

        $titles = collect($this->actingAs($hr)->get($this->showUrl('job-title'))->viewData('page')['props']['summary'][0]['rows'])->keyBy(0);
        $this->assertSame(2, $titles['Developer'][1]);
        $this->assertSame('Information Technology; Information Technology › Development', $titles['Developer'][2]);
    }

    #[Test]
    public function new_hires_use_joined_date_only(): void
    {
        $hr = $this->reportUser('hr_director');

        // default range: this month (Oct 1–7): Ana and Cara, not Ben (2025) nor anyone without joined_date
        $this->assertSame(['Diaz, Cara', 'Reyes, Ana'], collect($this->rowsOf($hr, 'new-hires'))->pluck('name')->all());
        $this->assertSame(['Cruz, Ben'], collect($this->rowsOf($hr, 'new-hires', ['joined_from' => '2025-01-01', 'joined_to' => '2025-12-31']))->pluck('name')->all());
    }

    #[Test]
    public function schedule_reports_use_assignments_weekdays_and_holidays(): void
    {
        $hr = $this->reportUser('hr_manager');
        $this->schedule($this->ana);
        Holiday::create(['name' => 'Foundation Day', 'date' => '2026-10-08', 'is_working_day' => false]);

        $assignments = $this->rowsOf($hr, 'schedule-assignments');
        $this->assertSame([['Reyes, Ana', 'Mon–Fri (M-F)', 'Mon,Tue,Wed,Thu,Fri 08:00–17:00 (Day)', 'In effect']],
            collect($assignments)->map(fn ($r) => [$r['name'], $r['schedule'], $r['weekly_pattern'], $r['state']])->all());

        $roster = collect($this->rowsOf($hr, 'daily-roster', ['date_from' => '2026-10-07', 'date_to' => '2026-10-10', 'search' => 'Reyes']));
        $this->assertSame([
            ['2026-10-07', 'Working day', '08:00'],
            ['2026-10-08', 'Holiday: Foundation Day', null],
            ['2026-10-09', 'Working day', '08:00'],
            ['2026-10-10', 'Rest day', null],
        ], $roster->map(fn ($r) => [$r['date'], $r['day_type'], $r['start_time']])->all());

        $this->actingAs($hr)->get($this->showUrl('daily-roster', ['date_from' => '2026-10-01', 'date_to' => '2026-11-15']))
            ->assertSessionHasErrors('date_to');
    }

    #[Test]
    public function attendance_reports_are_raw_and_flag_unregistered_ids(): void
    {
        $hr = $this->reportUser('hr_staff');
        $this->punch($this->ana, 'BIO-1', '2026-10-06', '08:01:00');
        $this->punch($this->ana, 'BIO-1', '2026-10-06', '17:05:00', 'out');
        $this->punch(null, 'BIO-X', '2026-10-06', '07:00:00');
        $this->punch($this->ben, 'BIO-2', '2026-09-01', '08:00:00'); // outside default range

        $log = collect($this->rowsOf($hr, 'attendance-log'));
        $this->assertSame(['Reyes, Ana', 'Reyes, Ana', 'Unregistered ID'], $log->pluck('employee')->all());
        $this->assertSame('SN-001', $log[0]['device_serial']);
        $this->assertSame(['Unregistered ID'], collect($this->rowsOf($hr, 'attendance-log', ['unregistered_only' => 1]))->pluck('employee')->all());

        $summary = collect($this->rowsOf($hr, 'attendance-summary'))->keyBy('biometric_id');
        $this->assertSame([2, '08:01:00', '17:05:00'], [$summary['BIO-1']['punches'], $summary['BIO-1']['first_punch'], $summary['BIO-1']['last_punch']]);
        $this->assertArrayNotHasKey('late', $summary['BIO-1']);

        $this->actingAs($hr)->get($this->showUrl('attendance-summary'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('report.title', 'Raw Attendance Summary')
                ->where('notes.0', fn ($note) => str_contains($note, 'not calculated')));

        $this->actingAs($hr)->get($this->showUrl('attendance-log', ['date_from' => '2026-01-01', 'date_to' => '2026-10-07']))->assertSessionHasErrors('date_to');
    }

    #[Test]
    public function leave_reports_use_approved_day_fractions_and_the_balance_rule(): void
    {
        $hr = $this->reportUser('payroll');
        $this->approvedLeave($this->ana, ['2026-09-10' => 1.0, '2026-09-11' => 0.5]);
        $this->approvedLeave($this->ana, ['2026-09-20' => 1.0], 'rejected');
        $this->balance($this->ana, 10, 2, 1.5);

        $applications = collect($this->rowsOf($hr, 'leave-applications'));
        $this->assertCount(2, $applications);
        $this->assertSame(['2026-09-10', '2026-09-11', 1.5], [$applications->firstWhere('status', 'Approved')['first_date'], $applications->firstWhere('status', 'Approved')['last_date'], $applications->firstWhere('status', 'Approved')['days']]);
        $this->assertCount(1, $this->rowsOf($hr, 'leave-applications', ['application_status' => 'rejected']));

        $usage = $this->rowsOf($hr, 'leave-usage');
        $this->assertSame([['Reyes, Ana', 2, 1.5]], collect($usage)->map(fn ($r) => [$r['employee'], $r['dates'], $r['days_used']])->all());

        $balance = $this->rowsOf($hr, 'leave-balance');
        $this->assertSame([10.0, 2.0, 8.0, 1.5], [$balance[0]['balance'], $balance[0]['pending'], $balance[0]['available'], $balance[0]['used']]);
    }

    #[Test]
    public function user_access_reports_never_expose_secrets(): void
    {
        $director = $this->userWithRole('hr_director');
        $director->forceFill(['google_id' => 'google-secret-123'])->save();
        $orphan = User::factory()->create(['username' => 'orphan.account']);

        $response = $this->actingAs($director)->get($this->showUrl('user-accounts'))->assertOk();
        $json = json_encode($response->viewData('page')['props']['rows']);

        foreach ([$director->password, 'google-secret-123', $director->remember_token] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertStringNotContainsString('password', $json);
        $this->assertSame(['username', 'status', 'employee_number', 'employee', 'roles', 'two_factor', 'last_login'], array_keys($response->viewData('page')['props']['rows']['data'][0]));

        $roles = collect($this->rowsOf($director, 'role-permissions'))->keyBy('role');
        $this->assertSame(1, $roles['hr_director']['users']);
        $this->assertStringContainsString('report.user_access', $roles['hr_director']['permissions']);

        $this->assertSame(['orphan.account'], collect($this->rowsOf($director, 'account-matching', ['view' => 'accounts_without_employees']))->pluck('username')->all());
        $withoutAccounts = collect($this->rowsOf($director, 'account-matching'))->pluck('employee')->all();
        $this->assertContains('Reyes, Ana', $withoutAccounts);
        $this->assertNotContains($director->employee->emp_last_name.', '.$director->employee->emp_first_name, $withoutAccounts);
    }

    // ---------------------------------------------------------------
    // Empty results and a smoke run of every report and format
    // ---------------------------------------------------------------

    #[Test]
    public function empty_results_are_handled(): void
    {
        $hr = $this->reportUser('hr_director');

        $this->actingAs($hr)->get($this->showUrl('employee-masterlist', ['search' => 'nobody-matches']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('rows.total', 0)->where('rows.data', []));

        $this->actingAs($hr)->get(route('reports.attendance-log.print'))->assertOk()->assertSee('No records match the selected filters.');

        // nobody is on leave: every aggregate section is empty
        $this->actingAs($hr)->get($this->showUrl('employee-demographics', ['status' => 'on_leave']))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('summary.0.rows', []));
    }

    #[Test]
    #[DataProvider('reports')]
    public function every_report_renders_and_exports(string $key, string $permission): void
    {
        Excel::fake();
        $this->schedule($this->ana);
        $this->punch($this->ana, 'BIO-1', '2026-10-06', '08:01:00');
        $this->approvedLeave($this->ana, ['2026-09-10' => 1.0]);
        $this->balance($this->ana, 5, 0, 1);

        $superadmin = $this->reportUser('superadmin');

        $this->actingAs($superadmin)->get($this->showUrl($key))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('app/Reports/Show', false)->where('report.key', $key));
        $this->actingAs($superadmin)->get(route("reports.{$key}.excel"))->assertOk();
        $this->actingAs($superadmin)->get(route("reports.{$key}.print"))->assertOk();
        $this->actingAs($superadmin)->get(route("reports.{$key}.pdf"))->assertOk();
    }
}
