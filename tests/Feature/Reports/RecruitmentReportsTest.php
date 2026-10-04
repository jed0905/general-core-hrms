<?php

namespace Tests\Feature\Reports;

use App\Exports\ReportExport;
use App\Models\Application;
use App\Models\ApplicationScreening;
use App\Models\Department;
use App\Models\JobOffer;
use App\Models\RecruitmentSource;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Recruitment\ApplicationPipelineService;
use App\Services\Recruitment\OfferService;
use App\Services\Reports\ReportRegistry;
use Database\Seeders\EmployeeMovementTypeSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Feature\Recruitment\RecruitmentTestCase;

/**
 * Phase 8 recruitment reports on the shared report framework.
 *
 * Fixture timeline (Carbon test time): applications on 2026-10-04,
 * interviews and everything after them on 2026-10-05.
 */
class RecruitmentReportsTest extends RecruitmentTestCase
{
    public const KEYS = [
        'recruitment-funnel', 'recruitment-vacancies', 'vacancy-aging', 'recruitment-sources', 'recruitment-screening',
        'recruitment-interviews', 'recruitment-assessments', 'recruitment-selection', 'recruitment-offers', 'time-to-fill',
        'time-to-hire', 'recruitment-conversion', 'recruitment-workload', 'careers-portal',
    ];

    protected const PERIOD = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31'];

    protected ?User $offerManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EmployeeMovementTypeSeeder::class);
    }

    // ------------------------------------------------------------- helpers

    protected function summary(string $key, array $filters = self::PERIOD): array
    {
        $report = ReportRegistry::resolve($key);

        return collect($report->summary($report->normalize($filters)))
            ->mapWithKeys(fn ($section) => [$section['title'] => $section['rows']])->all();
    }

    protected function section(string $key, string $titleStart, array $filters = self::PERIOD): array
    {
        foreach ($this->summary($key, $filters) as $title => $rows) {
            if (str_starts_with($title, $titleStart)) {
                return $rows;
            }
        }
        $this->fail("No section \"{$titleStart}\" in {$key}.");
    }

    /** First-column label => remaining cells. */
    protected function keyed(array $rows): array
    {
        return collect($rows)->mapWithKeys(fn ($r) => [$r[0] => array_slice($r, 1)])->all();
    }

    /** An application taken all the way: screening → … → accepted offer → employee. */
    protected function hired(Vacancy $vacancy): Application
    {
        $this->offerManager ??= $this->hrApprovalChain();
        $application = $this->evaluated($vacancy);
        $this->select($application);
        $offer = $this->approvedOffer($application->fresh(), $this->offerManager);
        app(OfferService::class)->issue($offer->fresh(), null, $this->hrStaff);
        app(OfferService::class)->respond($offer->fresh(), 'accepted', null, $this->hrStaff);
        $this->actingAs($this->dina)->post(route('recruitment.applications.convert', $application), ['emp_sex' => 'female'])->assertSessionHasNoErrors();

        return $application->fresh();
    }

    protected function at(string $time, callable $callback): mixed
    {
        $previous = Carbon::now();
        Carbon::setTestNow($time);
        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);
        }
    }

    // ------------------------------------------------------------- access

    #[Test]
    public function hr_roles_can_open_and_export_every_recruitment_report(): void
    {
        $this->openVacancy();

        foreach (['superadmin', 'hr_director', 'hr_manager', 'hr_staff'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            foreach (self::KEYS as $key) {
                $this->actingAs($user)->get(route("reports.{$key}.show", self::PERIOD))->assertOk();
            }
            $this->actingAs($user)->get(route('reports.recruitment-funnel.print', self::PERIOD))->assertOk();
            $catalog = collect($this->actingAs($user)->get(route('reports.index'))->viewData('page')['props']['categories']);
            $this->assertSame(self::KEYS, collect($catalog->firstWhere('key', 'recruitment')['reports'])->pluck('key')->all(), $role);
        }
    }

    #[Test]
    public function other_roles_cannot_open_or_export_recruitment_reports(): void
    {
        // A hiring manager (supervisor) gets no recruitment reporting from that role.
        foreach (['supervisor', 'payroll', 'employee'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            foreach (self::KEYS as $key) {
                foreach (['show', 'excel', 'pdf', 'print'] as $format) {
                    $this->actingAs($user)->get(route("reports.{$key}.{$format}", self::PERIOD))->assertForbidden();
                }
            }
        }

        $this->assertFalse($this->ramon->can('report.recruitment'));
    }

    #[Test]
    public function exports_also_need_report_export(): void
    {
        Role::findByName('hr_staff')->revokePermissionTo('report.export');
        $staff = $this->hrStaff->fresh();

        $this->actingAs($staff)->get(route('reports.recruitment-offers.show', self::PERIOD))->assertOk();
        foreach (['excel', 'pdf', 'print'] as $format) {
            $this->actingAs($staff)->get(route("reports.recruitment-offers.{$format}", self::PERIOD))->assertForbidden();
        }
    }

    #[Test]
    public function the_permission_is_granted_to_hr_roles_only(): void
    {
        foreach (['superadmin', 'hr_director', 'hr_manager', 'hr_staff'] as $role) {
            $this->assertTrue(Role::findByName($role)->hasPermissionTo('report.recruitment'), $role);
        }
        foreach (['payroll', 'supervisor', 'employee'] as $role) {
            $this->assertFalse(Role::findByName($role)->hasPermissionTo('report.recruitment'), $role);
        }
    }

    #[Test]
    public function filters_are_validated(): void
    {
        $this->actingAs($this->dina)->get(route('reports.recruitment-funnel.show', ['date_from' => '2026-10-10', 'date_to' => '2026-10-01']))
            ->assertSessionHasErrors('date_to');
        $this->actingAs($this->dina)->get(route('reports.recruitment-sources.show', self::PERIOD + ['recruitment_source_id' => 999999]))
            ->assertSessionHasErrors('recruitment_source_id');
        $this->actingAs($this->dina)->get(route('reports.recruitment-vacancies.show', self::PERIOD + ['vacancy_status' => 'bogus']))
            ->assertSessionHasErrors('vacancy_status');
    }

    // ------------------------------------------------------------- funnel

    #[Test]
    public function the_funnel_counts_distinct_applications_and_keeps_cohort_activity_and_current_apart(): void
    {
        $vacancy = $this->openVacancy();
        $this->inScreening($vacancy, ApplicationScreening::RESULT_FAILED);     // screening only (failing rejects it)
        $rejected = $this->inScreening($vacancy);
        app(ApplicationPipelineService::class)->reject($rejected, $this->reason()->id, $this->hrStaff);
        $this->applyTo($vacancy);                                              // still at Applied
        $this->hired($vacancy);                                                // reaches every step (moves the clock to 10-05)

        $cohort = $this->keyed($this->section('recruitment-funnel', 'Cohort Funnel'));
        $this->assertSame([4, '100.0%', '—'], $cohort['Applied']);
        $this->assertSame(3, $cohort['Reached Screening'][0]);
        foreach (['Reached Shortlisted', 'Reached Interview', 'Reached Assessment', 'Reached Evaluation', 'Selected', 'Offer issued', 'Offer accepted', 'Hired (converted)'] as $step) {
            $this->assertSame(1, $cohort[$step][0], $step); // one application, however many history rows
        }
        $this->assertSame('33.3%', $cohort['Reached Shortlisted'][2]);

        $activity = $this->keyed($this->section('recruitment-funnel', 'Activity'));
        $this->assertSame([4], $activity['Applications received']);
        $this->assertSame([2], $activity['Rejected']);

        $current = $this->keyed($this->section('recruitment-funnel', 'Current'));
        $this->assertSame([1], $current['In Applied']);
        $this->assertSame([0], $current['In Screening']);
        $this->assertSame([2], $current['Rejected (closed)']);

        // Cohort is by application date: a period after the applications has no cohort but still has activity.
        $later = ['date_from' => '2026-10-05', 'date_to' => '2026-10-31'];
        $this->assertSame(0, $this->keyed($this->section('recruitment-funnel', 'Cohort Funnel', $later))['Applied'][0]);
        $this->assertSame([1], $this->keyed($this->section('recruitment-funnel', 'Activity', $later))['Offer accepted']);
        $this->assertSame([0], $this->keyed($this->section('recruitment-funnel', 'Activity', $later))['Applications received']);
    }

    #[Test]
    public function date_ranges_include_both_whole_days(): void
    {
        $vacancy = $this->openVacancy();
        foreach (['2026-10-03 23:59:59', '2026-10-04 00:00:00', '2026-10-04 23:59:59', '2026-10-05 00:00:00'] as $time) {
            $this->at($time, fn () => $this->applyTo($vacancy));
        }

        $day = ['date_from' => '2026-10-04', 'date_to' => '2026-10-04'];
        $this->assertSame(2, $this->keyed($this->section('recruitment-funnel', 'Cohort Funnel', $day))['Applied'][0]);
        $this->assertSame([2], $this->keyed($this->section('recruitment-funnel', 'Activity', $day))['Applications received']);
        $this->assertSame(4, $this->keyed($this->section('recruitment-funnel', 'Cohort Funnel', ['date_from' => '2026-10-03', 'date_to' => '2026-10-05']))['Applied'][0]);
    }

    // ------------------------------------------------------------- scope

    #[Test]
    public function department_filters_follow_the_vacancy_department_tree(): void
    {
        $platform = Department::create(['name' => 'Platform', 'shortcut' => 'PLT', 'parent_id' => $this->it->id]);
        $finance = Department::create(['name' => 'Finance', 'shortcut' => 'FIN']);
        $this->applyTo($this->openVacancy());
        $this->applyTo($this->openVacancy(['department_id' => $platform->id]));
        $this->applyTo($this->openVacancy(['department_id' => $finance->id]));

        $applied = fn (array $f) => $this->keyed($this->section('recruitment-funnel', 'Cohort Funnel', self::PERIOD + $f))['Applied'][0];
        $this->assertSame(3, $applied([]));
        $this->assertSame(2, $applied(['department_id' => $this->it->id]));
        $this->assertSame(1, $applied(['department_id' => $this->it->id, 'include_sub_departments' => '0']));
        $this->assertSame(1, $applied(['department_id' => $finance->id]));

        $this->actingAs($this->dina)->get(route('reports.recruitment-vacancies.show', self::PERIOD + ['department_id' => $finance->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('rows.total', 1)->where('rows.data.0.department', 'Finance'));
    }

    #[Test]
    public function sources_group_cohort_applications_and_show_missing_sources(): void
    {
        $vacancy = $this->openVacancy();
        $referral = RecruitmentSource::where('code', '!=', 'company_website')->orderBy('id')->firstOrFail();
        $this->applyTo($vacancy, null, ['recruitment_source_id' => $referral->id]);
        $this->applyTo($vacancy, null, ['recruitment_source_id' => $referral->id]);
        $this->applyTo($vacancy);

        $rows = $this->keyed($this->section('recruitment-sources', 'Applications by Source'));
        $this->assertSame([2, '66.7%', 0, 0, 0, 0, 0, '0.0%'], $rows[$referral->name]);
        $this->assertSame(1, $rows['Not specified'][0]);

        $only = $this->keyed($this->section('recruitment-sources', 'Applications by Source', self::PERIOD + ['recruitment_source_id' => 'none']));
        $this->assertSame(['Not specified'], array_keys($only));
    }

    // ------------------------------------------------------------- stage reports

    #[Test]
    public function screening_interviews_assessments_and_selection_are_counted(): void
    {
        $vacancy = $this->openVacancy(['hiring_manager_id' => $this->ramon->employee_id]);
        $this->hired($vacancy);
        $this->inScreening($vacancy, ApplicationScreening::RESULT_FAILED);
        $this->inScreening($vacancy); // awaiting a result

        $this->assertSame([[2, 1, 1, '50.0%']], $this->section('recruitment-screening', 'Screening Results'));
        $this->assertSame([[1]], $this->section('recruitment-screening', 'Awaiting'));

        $interviews = $this->section('recruitment-interviews', 'Interviews in the Period');
        $this->assertSame([1, 1, 0, 0, 0, '100.0%', 1], $interviews[0]);
        $this->assertSame(['Manager, Maya', 1, 1, 0], $this->section('recruitment-interviews', 'Panelist')[0]);

        $this->assertSame([1, 1, 0, '100.0%', 1, 1], $this->section('recruitment-assessments', 'Interview Scorecards')[0]);
        $this->assertSame(1, $this->keyed($this->section('recruitment-assessments', 'Assessments Created'))['Completed'][0]);

        $decisions = $this->keyed($this->section('recruitment-selection', 'Decisions'));
        $this->assertSame([1, 1], $decisions['Selected (By HR)']);
        $this->assertSame([$vacancy->vacancy_number.' · '.$vacancy->title, 2, 1, 1], $this->section('recruitment-selection', 'Vacancies with Decisions')[0]);
    }

    // ------------------------------------------------------------- offers, time, conversion

    #[Test]
    public function offers_time_to_fill_time_to_hire_and_conversion(): void
    {
        $vacancy = $this->openVacancy(['job_requisition_id' => $this->approvedRequisition()->id]);
        $this->hired($vacancy);
        $declined = $this->evaluated($vacancy);
        $this->select($declined);
        $offer = $this->approvedOffer($declined->fresh(), $this->offerManager);
        app(OfferService::class)->issue($offer->fresh(), null, $this->hrStaff);
        app(OfferService::class)->respond($offer->fresh(), 'declined', null, $this->hrStaff);

        $events = $this->keyed($this->section('recruitment-offers', 'Lifecycle'));
        $this->assertSame([2], $events['Issued to candidate']);
        $this->assertSame([1], $events['Accepted']);
        $this->assertSame([1], $events['Declined']);
        $this->assertSame([[2, 0, '100.0%']], $this->section('recruitment-offers', 'Approval')); // per offer, not per approver step
        $outcome = $this->keyed($this->section('recruitment-offers', 'Outcome'));
        $this->assertSame([1, '50.0%'], $outcome['Accepted']);
        $this->assertSame([0, '0.0%'], $outcome['Awaiting response']);

        // Vacancy opened 10-04, offer accepted 10-05; requisition approved 10-04.
        $fill = $this->keyed($this->section('time-to-fill', 'Time to Fill'));
        $this->assertSame([1, '1.0', '1.0', '1', '1'], $fill['Vacancy opened → offer accepted']);
        $this->assertSame([1, '1.0', '1.0', '1', '1'], $fill['Requisition approved → offer accepted']);
        $this->actingAs($this->dina)->get(route('reports.time-to-fill.show', self::PERIOD))
            ->assertInertia(fn ($page) => $page->where('rows.total', 1)->where('rows.data.0.days_from_opening', 1));

        $hire = $this->keyed($this->section('time-to-hire', 'Time to Hire'));
        $this->assertSame([1, '1.0', '1.0', '1', '1'], $hire['Applied → offer accepted']);
        $this->assertSame(1, $hire['Applied → converted to employee'][0]);

        $this->assertSame([[1, 1, 0, '100.0%', '0.0', '0.0']], $this->section('recruitment-conversion', 'Offers Accepted'));
        $this->assertSame([['New employee', 1, '100.0%']], $this->section('recruitment-conversion', 'Conversions in the Period by Type'));

        // A period before the acceptance has no fills.
        $this->assertSame(0, $this->keyed($this->section('time-to-fill', 'Time to Fill', ['date_from' => '2026-10-01', 'date_to' => '2026-10-04']))['Vacancy opened → offer accepted'][0]);
    }

    #[Test]
    public function vacancy_aging_and_workload(): void
    {
        $this->at('2026-08-01 09:00:00', fn () => $this->openVacancy(['hiring_manager_id' => $this->ramon->employee_id, 'closing_date' => '2026-09-30']));
        $this->openVacancy(['hiring_manager_id' => $this->ramon->employee_id]);
        $this->applyTo(Vacancy::latest('id')->first());

        $buckets = $this->keyed($this->section('vacancy-aging', 'Open Vacancies by Age'));
        $this->assertSame(1, $buckets['0–30 days'][0]);
        $this->assertSame(1, $buckets['61–90 days'][0]); // 64 days
        $this->assertSame([2, '32.0', '32.0', '0', '64', 1], $this->section('vacancy-aging', 'Age Statistics')[0]);

        $managers = $this->keyed($this->section('recruitment-workload', 'Hiring Managers'));
        $this->assertSame([2, 4, 1, 0, 0, 0], $managers['Requester, Ramon']);
        $hr = $this->keyed($this->section('recruitment-workload', 'HR Actions'));
        $this->assertSame(1, $hr['Staff, Hana'][0]); // applications entered
    }

    #[Test]
    public function careers_portal_counts_online_applications_separately(): void
    {
        $vacancy = $this->openVacancy();
        $this->applyTo($vacancy);
        $online = $this->applyTo($vacancy);
        Application::whereKey($online->id)->update(['created_by' => null]); // as recorded by the portal
        DB::table('recruitment_portal_events')->insert([
            'event' => 'application_submitted', 'application_id' => $online->id, 'vacancy_id' => $vacancy->id, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame([[2, 1, 1, '50.0%']], $this->section('careers-portal', 'Online Share'));
        $this->assertSame([1, 0], $this->keyed($this->section('careers-portal', 'Portal Activity'))['Applications submitted online']);
    }

    // ------------------------------------------------------------- privacy, export, read-only

    #[Test]
    public function reports_and_exports_never_contain_candidate_details(): void
    {
        $vacancy = $this->openVacancy(['hiring_manager_id' => $this->ramon->employee_id]);
        $hired = $this->hired($vacancy);
        $applicant = $hired->applicant;
        $offer = JobOffer::where('application_id', $hired->id)->first();
        $secrets = [$applicant->first_name.' '.$applicant->last_name, $applicant->last_name, $applicant->email, $applicant->phone, $hired->application_number, $offer->offer_number, '55,000', '55000', 'Went well.', 'Strongest panel feedback.'];

        foreach (self::KEYS as $key) {
            $page = $this->actingAs($this->dina)->get(route("reports.{$key}.show", self::PERIOD))->assertOk();
            $print = $this->actingAs($this->dina)->get(route("reports.{$key}.print", self::PERIOD))->assertOk();
            foreach ($secrets as $secret) {
                $this->assertStringNotContainsString($secret, json_encode($page->viewData('page')['props']['rows'] ?? []), "{$key} rows");
                $this->assertStringNotContainsString($secret, json_encode($page->viewData('page')['props']['summary']), "{$key} summary");
                $this->assertStringNotContainsString($secret, $print->getContent(), "{$key} print");
            }
        }
    }

    #[Test]
    public function excel_export_uses_the_same_filters_and_summary(): void
    {
        Excel::fake();
        $this->applyTo($this->openVacancy());

        $this->actingAs($this->dina)->get(route('reports.recruitment-funnel.excel', ['date_from' => '2026-10-04', 'date_to' => '2026-10-04']))->assertOk();

        Excel::assertDownloaded('recruitment-funnel_20261004_090000.xlsx', function (ReportExport $export) {
            $cohort = collect($export->report->summary($export->filters))->firstWhere('title', 'Cohort Funnel (applications received in the period)');

            return $export->filters['date_from'] === '2026-10-04'
                && $cohort['rows'][0] === ['Applied', 1, '100.0%', '—']
                && count($export->sheets()) === 2; // summary + info
        });
    }

    #[Test]
    public function reports_only_read(): void
    {
        $this->hired($this->openVacancy());
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (! preg_match('/^\s*(select|pragma)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        foreach (self::KEYS as $key) {
            $report = ReportRegistry::resolve($key);
            $filters = $report->normalize(self::PERIOD);
            $report->summary($filters);
            if ($report->hasRows($filters)) {
                iterator_to_array($report->rows($filters));
            }
        }

        $this->assertSame([], $writes);
    }
}
