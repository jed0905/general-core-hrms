<?php

namespace Tests\Feature\Recruitment;

use App\Models\ApplicantDocumentType;
use App\Models\Application;
use App\Models\ApplicationConversion;
use App\Models\ApplicationConversionDocument;
use App\Models\Education;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeMovement;
use App\Models\EmployeeMovementType;
use App\Models\JobOffer;
use App\Models\NumberSequence;
use App\Models\Onboarding;
use App\Models\User;
use App\Models\WorkExperience;
use App\Services\EmployeeMovementService;
use App\Services\Onboarding\OnboardingTemplateService;
use App\Services\Recruitment\ApplicantDocumentService;
use App\Services\Recruitment\ApplicantService;
use App\Services\Recruitment\ApplicationConversionService;
use App\Services\Recruitment\ApplicationPipelineService;
use App\Services\Recruitment\ApplicationService;
use App\Services\Recruitment\AssessmentService;
use App\Services\Recruitment\EvaluationService;
use App\Services\Recruitment\OfferApprovalService;
use App\Services\Recruitment\OfferService;
use App\Services\Recruitment\ScreeningService;
use Database\Seeders\EmployeeMovementTypeSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 5: converting an application with an accepted offer into a Core HR employee.
 */
class ApplicationConversionTest extends RecruitmentTestCase
{
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(EmployeeMovementTypeSeeder::class); // Core HR reference data (the "hiring" type)
        $this->manager = $this->hrApprovalChain();
    }

    /** A selected candidate with history, documents and an offer in the given state. */
    private function candidate(string $offerState = 'accepted', array $applicantOverrides = []): Application
    {
        $applicant = app(ApplicantService::class)->createApplicant($this->applicantPayload(array_merge([
            'first_name' => 'Carla', 'middle_name' => 'M.', 'last_name' => 'Cruz', 'suffix' => null,
            'email' => 'carla.cruz@example.test', 'phone' => '0917 123 4567', 'address' => '12 Mabini St, Pasig City',
            'education' => [
                ['level' => 'College', 'institute' => 'UP Diliman', 'degree' => 'BS Computer Science', 'major_specialization' => 'Software', 'start_date' => '2014-06-01', 'end_date' => '2018-04-30', 'is_completed' => true],
                ['level' => 'Masters', 'institute' => 'Ateneo', 'degree' => 'MS IT', 'start_date' => '2019-06-01', 'end_date' => '2021-04-30', 'is_completed' => true, 'notes' => 'Thesis on HR systems'],
            ],
            'work_experience' => [
                ['company' => 'Acme Corp', 'job_title' => 'Developer', 'from' => '2018-05-01', 'to' => '2022-12-31', 'notes' => 'Laravel'],
                ['company' => 'Beta Inc', 'job_title' => 'Senior Developer', 'from' => '2023-01-01', 'to' => '2025-12-31'],
                ['company' => 'Gamma LLC', 'job_title' => 'Lead', 'from' => '2026-01-01'], // current role, no end date
            ],
        ], $applicantOverrides)), $this->hrStaff);

        $docs = app(ApplicantDocumentService::class);
        $resume = $docs->upload($applicant, UploadedFile::fake()->create('carla-cv.pdf', 30, 'application/pdf'), ['applicant_document_type_id' => ApplicantDocumentType::where('code', 'resume')->value('id')], $this->hrStaff);
        $cert = $docs->upload($applicant, UploadedFile::fake()->create('cert.pdf', 10, 'application/pdf'), ['applicant_document_type_id' => ApplicantDocumentType::where('code', 'certificate')->value('id')], $this->hrStaff);
        $docs->upload($applicant, UploadedFile::fake()->create('not-submitted.pdf', 10, 'application/pdf'), ['applicant_document_type_id' => ApplicantDocumentType::where('code', 'other')->value('id')], $this->hrStaff);

        $vacancy = $this->openVacancy(['hiring_manager_id' => $this->ramon->employee_id]);
        $application = $this->evaluatedFor($vacancy, $applicant);
        app(ApplicationService::class)->attachDocuments($application, [$resume->id, $cert->id], $this->hrStaff);

        if ($offerState === 'selected') {
            return $this->selectAndReturn($application);
        }
        $this->select($application);
        $offer = $this->draftOffer($application);
        $this->advanceOffer($offer, $offerState);

        return $application->fresh();
    }

    private function evaluatedFor($vacancy, $applicant): Application
    {
        $application = $this->applyTo($vacancy, $applicant);
        app(ApplicationPipelineService::class)->advance($application, $application->current_vacancy_stage_id, $this->hrStaff);
        app(ScreeningService::class)->record($application, ['result' => 'passed', 'screened_on' => '2026-10-04'], $this->hrStaff);
        $application = app(ApplicationPipelineService::class)->shortlist($application, $application->fresh()->current_vacancy_stage_id, $this->hrStaff)->fresh();
        $application = $this->moveOn($application);
        $interview = $this->completedInterview($application, [$this->maya]);
        app(EvaluationService::class)->submit($interview, ['recommendation' => 'recommend', 'scores' => $this->scores(4)], $this->maya);
        $application = $this->moveOn($application);
        $assessment = app(AssessmentService::class)->create($application, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);
        app(AssessmentService::class)->complete($assessment, ['score' => 9, 'maximum_score' => 10], $this->hrStaff);

        return $this->moveOn($application);
    }

    private function selectAndReturn(Application $application): Application
    {
        $this->select($application);

        return $application->fresh();
    }

    private function advanceOffer(JobOffer $offer, string $state): void
    {
        $offers = app(OfferService::class);
        if ($state === 'draft') {
            return;
        }
        if ($state === 'withdrawn') {
            $offers->withdraw($offer, 'x', $this->hrStaff);

            return;
        }
        $offers->submit($offer, null, $this->hrStaff);
        if ($state === 'pending_approval') {
            return;
        }
        if ($state === 'rejected') {
            app(OfferApprovalService::class)->reject($offer, $this->manager, 'No');

            return;
        }
        app(OfferApprovalService::class)->approve($offer, $this->manager);
        app(OfferApprovalService::class)->approve($offer, $this->dina);
        if ($state === 'approved') {
            return;
        }
        $offers->issue($offer, null, $this->hrStaff);
        if ($state === 'issued') {
            return;
        }
        if ($state === 'expired') {
            Carbon::setTestNow('2026-10-25 09:00:00');
            $offers->expireIfDue($offer);

            return;
        }
        $offers->respond($offer, $state === 'declined' ? 'declined' : 'accepted', null, $this->hrStaff);
    }

    private function convert(User $as, Application $application, array $data = [])
    {
        return $this->actingAs($as)->post(route('recruitment.applications.convert', $application), array_merge(['emp_sex' => 'female'], $data));
    }

    private function counts(): array
    {
        return [
            'employees' => Employee::count(), 'users' => User::count(), 'movements' => EmployeeMovement::count(),
            'educations' => Education::count(), 'work' => WorkExperience::count(), 'documents' => EmployeeDocument::count(),
            'conversions' => ApplicationConversion::count(), 'next_number' => NumberSequence::where('key', 'employee_number')->value('next_number'),
        ];
    }

    // ------------------------------------------------------------- A. eligibility

    #[Test]
    public function only_an_accepted_offer_can_be_converted(): void
    {
        foreach (['selected', 'draft', 'pending_approval', 'approved', 'issued', 'rejected', 'declined', 'withdrawn', 'expired'] as $state) {
            $app = $this->candidate($state, ['email' => "{$state}@example.test", 'phone' => null]);
            $before = $this->counts();
            $this->convert($this->dina, $app)->assertSessionHasErrors(['conversion' => 'Only accepted offers can be converted.']);
            $this->assertSame($before, $this->counts(), $state);
            Carbon::setTestNow('2026-10-05 12:00:00');
        }
    }

    // ------------------------------------------------------------- B. authorization

    #[Test]
    public function only_hr_with_conversion_and_core_hr_permissions_converts(): void
    {
        $app = $this->candidate();
        $employee = $this->userWithRole('employee');
        $payroll = $this->userWithRole('payroll');

        foreach ([$this->ramon, $this->maya, $this->outsider, $employee, $payroll] as $user) {
            $this->convert($user, $app)->assertForbidden();
        }
        // An HR user missing the Core HR capability is refused even with the recruitment permission.
        $limited = $this->userWithRole('hr_staff');
        Role::findByName('hr_staff')->revokePermissionTo('employee_movement.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->convert($limited->fresh(), $app)->assertForbidden();
        try {
            app(ApplicationConversionService::class)->convert($app, ['emp_sex' => 'female'], $limited->fresh());
            $this->fail('converted without employee_movement.create');
        } catch (ValidationException) {
        }
        $this->assertSame(0, ApplicationConversion::count());

        // The hiring manager can view the conversion section, not convert.
        $this->actingAs($this->ramon)->get(route('recruitment.applications.show', $app))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('conversion.canConvert', false)->where('conversion.problem', null));
    }

    // ------------------------------------------------------------- C-H. the conversion

    #[Test]
    public function an_accepted_candidate_becomes_an_employee_with_history_documents_and_a_hiring_movement(): void
    {
        $app = $this->candidate();
        $offer = JobOffer::where('application_id', $app->id)->sole();
        $documentIds = $app->documents()->pluck('applicant_documents.id')->all();
        $before = $this->counts();

        $this->actingAs($this->hrStaff)->get(route('recruitment.applications.show', $app))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('conversion.canConvert', true)->has('conversion.documents', 2)->where('conversion.canCreateAccount', false));

        $this->convert($this->hrStaff, $app, ['supervisor_id' => $this->ramon->employee_id, 'document_ids' => $documentIds])->assertSessionHasNoErrors();

        $conversion = ApplicationConversion::sole();
        $employee = Employee::findOrFail($conversion->employee_id);

        // C. employee + number + mapping
        $this->assertSame('EMP-00001', $employee->employee_number);
        $this->assertSame(
            ['Carla', 'M.', 'Cruz', 'female', 'carla.cruz@example.test', '0917 123 4567', '12 Mabini St, Pasig City', '2026-11-02', $this->developer->id, $this->it->id, $this->mainOffice->id, $this->regular->id, $this->ramon->employee_id, 'active'],
            [$employee->emp_first_name, $employee->emp_middle_name, $employee->emp_last_name, $employee->emp_sex, $employee->other_email, $employee->mobile_no, $employee->street1,
                (string) $employee->joined_date, $employee->job_title_id, $employee->department_id, $employee->location_id, $employee->employment_status_id, $employee->supervisor_id, $employee->status]
        );
        $this->assertNull($employee->work_email, 'the applicant email is personal, not a work email');

        // D. education (both rows; degree and major combined), originals kept
        $this->assertSame([['College', 'UP Diliman', 'BS Computer Science, Software'], ['Masters', 'Ateneo', 'MS IT']],
            $employee->education()->orderBy('id')->get()->map(fn ($e) => [$e->level, $e->institute, $e->major_specialization])->all());
        $this->assertSame(2, $app->applicant->education()->count());

        // E. work experience (ended roles only), originals kept
        $this->assertSame([['Acme Corp', 'Developer', 'Laravel'], ['Beta Inc', 'Senior Developer', null]],
            $employee->workExperience()->orderBy('id')->get()->map(fn ($w) => [$w->company, $w->job_title, $w->notes])->all());
        $this->assertSame(3, $app->applicant->workExperience()->count());

        // F. documents: submitted + mapped type only, private, provenance, originals untouched
        $copied = $employee->documents()->orderBy('id')->get();
        $this->assertSame(['carla-cv.pdf', 'cert.pdf'], $copied->pluck('original_name')->all());
        $this->assertSame(['recruitment', 'recruitment'], $copied->pluck('source')->all());
        $this->assertSame(['local', 'local'], $copied->pluck('disk')->all());
        foreach ($copied as $doc) {
            Storage::disk('local')->assertExists($doc->file_path);
            $this->assertStringStartsWith("employee-documents/{$employee->id}/", $doc->file_path);
            $this->assertStringNotContainsString('carla', $doc->file_path);
        }
        $this->assertSame(3, $app->applicant->documents()->count());
        foreach ($app->applicant->documents as $original) {
            Storage::disk('local')->assertExists($original->file_path);
        }
        $this->assertEqualsCanonicalizing($documentIds, ApplicationConversionDocument::pluck('applicant_document_id')->all());
        $this->assertArrayNotHasKey('file_path', $copied->first()->toArray(), 'storage paths are never serialized');

        // G. exactly one Hiring movement through the movement service (future start → scheduled)
        $movement = EmployeeMovement::sole();
        $this->assertSame([$employee->id, 'hiring', '2026-11-02', EmployeeMovement::STATUS_SCHEDULED, 'OFF-2026-00001', $this->hrStaff->id, []],
            [$movement->employee_id, $movement->type->code, $movement->effective_date->toDateString(), $movement->status, $movement->reference_number, $movement->created_by, $movement->changed_fields]);

        // H. conversion record, offer still accepted, nothing else created
        $this->assertSame(
            [$app->id, $app->applicant_id, $offer->id, 'new_employee', 'EMP-00001', $movement->id, '2026-11-02', 'none', null, 2, 2, 2, $this->hrStaff->id],
            [$conversion->application_id, $conversion->applicant_id, $conversion->job_offer_id, $conversion->conversion_type, $conversion->employee_number, $conversion->employee_movement_id,
                $conversion->start_date->toDateString(), $conversion->user_account, $conversion->user_id, $conversion->education_copied, $conversion->work_experience_copied, $conversion->documents_copied, $conversion->converted_by]
        );
        $this->assertNotNull($conversion->converted_at);
        $this->assertContains('Work experience at Gamma LLC has no end date (Core HR requires one) and was not copied.', $conversion->notices);
        $this->assertSame('accepted', $offer->fresh()->status);
        $this->assertSame('converted', $offer->events()->get()->last()->event);
        $this->assertSame($employee->id, $app->applicant->fresh()->converted_employee_id);
        $this->assertSame(1, $app->vacancy->fresh()->filled_count, 'selection already took the opening');
        $this->assertSame(['employees' => $before['employees'] + 1, 'users' => $before['users'], 'movements' => 1, 'educations' => 2, 'work' => 2, 'documents' => 2, 'conversions' => 1, 'next_number' => 2], $this->counts());

        // Result shown on the application page.
        $this->actingAs($this->hrStaff)->get(route('recruitment.applications.show', $app))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('conversion.record.employee_number', 'EMP-00001')->where('conversion.record.movement.type.name', 'Hiring'));
    }

    #[Test]
    public function documents_are_only_copied_when_chosen_and_must_belong_to_the_application(): void
    {
        $app = $this->candidate();
        $notSubmitted = $app->applicant->documents()->where('original_name', 'not-submitted.pdf')->value('id');

        $this->convert($this->hrStaff, $app, ['document_ids' => [$notSubmitted]])->assertSessionHasErrors('document_ids');
        $this->assertSame(0, Employee::where('emp_first_name', 'Carla')->count());

        $this->convert($this->hrStaff, $app)->assertSessionHasNoErrors(); // none chosen → none copied
        $this->assertSame([0, 0], [EmployeeDocument::count(), ApplicationConversion::sole()->documents_copied]);
    }

    #[Test]
    public function the_employee_sex_is_required_because_recruitment_never_collects_it(): void
    {
        $app = $this->candidate();
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.convert', $app), [])->assertSessionHasErrors('emp_sex');
        $this->assertSame(0, ApplicationConversion::count());
    }

    // ------------------------------------------------------------- I. no re-conversion

    #[Test]
    public function a_converted_application_can_never_be_converted_again(): void
    {
        $app = $this->candidate();
        $this->convert($this->hrStaff, $app)->assertSessionHasNoErrors();
        $after = $this->counts();

        $this->convert($this->dina, $app)->assertSessionHasErrors(['conversion' => 'This candidate has already been converted.']);
        try {
            app(ApplicationConversionService::class)->convert($app, ['emp_sex' => 'female'], $this->hrStaff);
            $this->fail('converted twice');
        } catch (ValidationException) {
        }
        $this->assertSame($after, $this->counts());

        $conversion = ApplicationConversion::sole();
        $this->expectException(LogicException::class);
        $conversion->update(['employee_number' => 'X']);
    }

    // ------------------------------------------------------------- J. rollback

    #[Test]
    public function a_failure_midway_rolls_everything_back_including_the_number_and_copied_files(): void
    {
        $app = $this->candidate();
        $docs = $app->documents()->orderBy('applicant_documents.id')->get();
        Storage::disk('local')->delete($docs[1]->file_path); // second file missing: fails after the first copy
        $before = $this->counts();
        $filesBefore = Storage::disk('local')->allFiles('employee-documents');

        $this->convert($this->hrStaff, $app, ['document_ids' => $docs->pluck('id')->all(), 'create_user_account' => false])
            ->assertSessionHasErrors(['conversion' => 'The conversion could not be completed, so nothing was saved: no employee, employee number, movement or account was created. Please try again or contact your administrator.']);

        $this->assertSame($before, $this->counts());
        $this->assertSame($filesBefore, Storage::disk('local')->allFiles('employee-documents'), 'no orphaned copies');
        $this->assertNull($app->applicant->fresh()->converted_employee_id);
        $this->assertSame('accepted', JobOffer::where('application_id', $app->id)->value('status'));
    }

    #[Test]
    public function a_failing_hiring_movement_rolls_back_the_employee(): void
    {
        $app = $this->candidate();
        EmployeeMovementType::where('code', 'hiring')->update(['is_active' => false]);
        $before = $this->counts();

        $this->convert($this->hrStaff, $app)->assertSessionHasErrors(['conversion' => 'The "Hiring" employee movement type is missing or inactive. No conversion was saved.']);
        $this->assertSame($before, $this->counts());
    }

    #[Test]
    public function a_movement_failure_after_the_employee_was_created_rolls_everything_back(): void
    {
        $app = $this->candidate();
        $before = $this->counts();

        // The employee (and its number) are created first; then the movement fails.
        $movements = \Mockery::mock(EmployeeMovementService::class);
        $movements->shouldReceive('createMovement')->once()->andReturnUsing(function (array $data) {
            $this->assertNotNull(Employee::find($data['employee_id']), 'the employee exists inside the transaction');
            throw new \RuntimeException('Simulated movement failure');
        });
        $this->app->instance(EmployeeMovementService::class, $movements);

        $this->convert($this->hrStaff, $app)
            ->assertSessionHasErrors(['conversion' => 'The conversion could not be completed, so nothing was saved: no employee, employee number, movement or account was created. Please try again or contact your administrator.']);

        $this->assertSame($before, $this->counts(), 'no employee, number, movement, history, document or conversion is left behind');
        $this->assertNull($app->applicant->fresh()->converted_employee_id);
    }

    #[Test]
    public function the_hiring_movement_uses_the_offer_start_date_reference_and_hired_assignment(): void
    {
        // Applied 10-04, interviewed and offered 10-05, accepted 10-07, starting 11-02.
        $app = $this->candidate();
        $offer = JobOffer::where('application_id', $app->id)->sole();
        $this->travelTo('2026-10-07 15:00:00');
        $offer->forceFill(['responded_at' => now()])->saveQuietly();

        $this->convert($this->hrStaff, $app)->assertSessionHasNoErrors();

        $employee = Employee::findOrFail(ApplicationConversion::sole()->employee_id);
        $movement = EmployeeMovement::where('employee_id', $employee->id)->sole();
        $dates = [$app->applied_at->toDateString(), $offer->created_at->toDateString(), '2026-10-07', '2026-10-07'];
        $this->assertSame('2026-11-02', $offer->proposed_start_date->toDateString());
        $this->assertNotContains('2026-11-02', $dates, 'the start date differs from every other date');
        $this->assertSame(['hiring', '2026-11-02', 'OFF-2026-00001', EmployeeMovement::STATUS_SCHEDULED, $employee->id],
            [$movement->type->code, $movement->effective_date->toDateString(), $movement->reference_number, $movement->status, $movement->employee_id]);

        // The scheduled hiring records the assignment from the accepted offer (not an empty record).
        $this->assertSame([$offer->job_title_id, $offer->department_id, $offer->employment_status_id, $offer->location_id, null],
            [$movement->to_job_title_id, $movement->to_department_id, $movement->to_employment_status_id, $movement->to_location_id, $movement->to_status]);
        $this->assertSame(['Software Developer', 'Information Technology', 'Regular'],
            [$movement->snapshot['to']['job_title'], $movement->snapshot['to']['department'], $movement->snapshot['to']['employment_status']]);
        $this->actingAs($this->dina)->get(route('people.employee-movements.show', $movement))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('movement.snapshot.to.job_title', 'Software Developer')->where('movement.reference_number', 'OFF-2026-00001'));
        $this->actingAs($this->dina)->get(route('people.employee-movements.index', ['employee_id' => $employee->id]))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('movements.total', 1)->where('movements.data.0.type.code', 'hiring'));

        // On the start date the daily job makes it effective, without changing the employee.
        $this->travelTo('2026-11-02 00:05:00');
        $this->artisan('employee-movements:apply-due')->assertSuccessful();
        $movement->refresh();
        $this->assertSame([EmployeeMovement::STATUS_EFFECTIVE, $offer->job_title_id], [$movement->status, $movement->to_job_title_id]);
        $this->assertSame($offer->job_title_id, $employee->fresh()->job_title_id);
    }

    #[Test]
    public function a_conversion_after_the_start_date_records_an_effective_hiring(): void
    {
        $app = $this->candidate();
        $this->travelTo('2026-11-05 09:00:00'); // HR converts three days after the start date

        $this->convert($this->hrStaff, $app)->assertSessionHasNoErrors();

        $movement = EmployeeMovement::sole();
        $this->assertSame(['2026-11-02', EmployeeMovement::STATUS_EFFECTIVE, 'OFF-2026-00001', 'Software Developer'],
            [$movement->effective_date->toDateString(), $movement->status, $movement->reference_number, $movement->snapshot['to']['job_title']]);
        $this->assertSame('2026-11-02', substr((string) Employee::findOrFail($movement->employee_id)->joined_date, 0, 10));
    }

    // ------------------------------------------------------------- K. internal candidate

    #[Test]
    public function an_internal_candidate_is_linked_without_creating_anything_in_core_hr(): void
    {
        $internal = $this->employee($this->outsider);
        $app = $this->candidate('accepted', ['is_internal' => true, 'employee_id' => $internal->id]);
        $before = $this->counts();

        $this->actingAs($this->hrStaff)->get(route('recruitment.applications.show', $app))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('conversion.existingEmployee.id', $internal->id)->has('conversion.documents', 0)->where('conversion.canCreateAccount', false));

        $this->convert($this->hrStaff, $app, ['emp_sex' => null])->assertSessionHasNoErrors();

        $conversion = ApplicationConversion::sole();
        $this->assertSame(['existing_employee', $internal->id, $internal->employee_number, null, 'existing', $this->outsider->id],
            [$conversion->conversion_type, $conversion->employee_id, $conversion->employee_number, $conversion->employee_movement_id, $conversion->user_account, $conversion->user_id]);
        $this->assertSame(array_merge($before, ['conversions' => 1]), $this->counts(), 'no employee, number, movement, history or document created');
        $this->assertSame($internal->job_title_id, $internal->fresh()->job_title_id, 'assignment unchanged');

        $this->convert($this->hrStaff, $app, ['emp_sex' => null, 'create_user_account' => true, 'username' => 'x', 'password' => 'Secret123!'])->assertSessionHasErrors();
    }

    #[Test]
    public function a_person_already_converted_through_another_application_is_not_hired_twice(): void
    {
        $first = $this->candidate();
        $this->convert($this->hrStaff, $first)->assertSessionHasNoErrors();
        $employeeId = ApplicationConversion::sole()->employee_id;

        // The same applicant applies, is selected and accepts on another vacancy.
        $second = $this->evaluatedFor($this->openVacancy(), $first->applicant->fresh());
        $this->select($second);
        $offer = $this->draftOffer($second);
        $this->advanceOffer($offer, 'accepted');
        $before = $this->counts();

        $this->convert($this->hrStaff, $second, ['emp_sex' => null])->assertSessionHasNoErrors();
        $this->assertSame(array_merge($before, ['conversions' => $before['conversions'] + 1]), $this->counts());
        $this->assertSame(['existing_employee', $employeeId], [ApplicationConversion::where('application_id', $second->id)->value('conversion_type'), ApplicationConversion::where('application_id', $second->id)->value('employee_id')]);
    }

    #[Test]
    public function a_terminated_linked_employee_is_not_rehired_by_conversion(): void
    {
        $internal = $this->employee($this->outsider);
        $app = $this->candidate('accepted', ['is_internal' => true, 'employee_id' => $internal->id]);
        Employee::whereKey($internal->id)->update(['status' => 'terminated']);

        $this->convert($this->hrStaff, $app, ['emp_sex' => null])->assertSessionHasErrors('conversion');
        $this->assertSame(0, ApplicationConversion::count());
    }

    // ------------------------------------------------------------- L. user account

    #[Test]
    public function a_login_is_created_only_when_asked_with_only_the_employee_role(): void
    {
        $app = $this->candidate();

        // HR staff can't create logins (no user.create); the director can.
        $this->convert($this->hrStaff, $app, ['create_user_account' => true, 'username' => 'carla.cruz', 'password' => 'Str0ng!Passw0rd'])->assertSessionHasErrors('conversion');
        $this->assertSame(0, ApplicationConversion::count());

        $this->convert($this->dina, $app, ['create_user_account' => true, 'username' => $this->hrStaff->username, 'password' => 'Str0ng!Passw0rd'])->assertSessionHasErrors('username');
        $this->convert($this->dina, $app, ['create_user_account' => true, 'username' => 'carla.cruz'])->assertSessionHasErrors('password');

        $this->convert($this->dina, $app, ['create_user_account' => true, 'username' => 'carla.cruz', 'password' => 'Str0ng!Passw0rd'])->assertSessionHasNoErrors();
        $conversion = ApplicationConversion::sole();
        $user = User::where('username', 'carla.cruz')->sole();
        $this->assertSame(['created', $user->id, $conversion->employee_id, 'active'], [$conversion->user_account, $conversion->user_id, $user->employee_id, $user->status]);
        $this->assertSame(['employee'], $user->getRoleNames()->all());
        $this->assertTrue(Hash::check('Str0ng!Passw0rd', $user->password));
        $this->assertFalse($user->can('recruitment.application.view'));
    }

    #[Test]
    public function no_login_is_created_unless_requested(): void
    {
        $app = $this->candidate();
        $users = User::count();
        $this->convert($this->dina, $app, ['username' => 'ignored', 'password' => 'Str0ng!Passw0rd'])->assertSessionHasNoErrors();
        $this->assertSame([$users, 'none'], [User::count(), ApplicationConversion::sole()->user_account]);
    }

    #[Test]
    public function the_offer_page_shows_the_conversion(): void
    {
        $app = $this->candidate();
        $this->convert($this->hrStaff, $app)->assertSessionHasNoErrors();
        $offer = JobOffer::where('application_id', $app->id)->sole();

        $this->actingAs($this->hrStaff)->get(route('recruitment.offers.show', $offer))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('conversion.employee_number', 'EMP-00001')->where('offer.status', 'accepted'));
    }

    #[Test]
    public function onboarding_is_started_separately_after_conversion_without_touching_core_hr_again(): void
    {
        $app = $this->candidate();
        $this->convert($this->hrStaff, $app)->assertSessionHasNoErrors();
        $conversion = ApplicationConversion::sole();
        $after = $this->counts();

        // Conversion never starts onboarding by itself; the result offers the explicit HR action.
        $this->assertSame(0, Onboarding::count());
        $this->actingAs($this->hrStaff)->get(route('recruitment.applications.show', $app))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('conversion.canStartOnboarding', true)->where('conversion.onboarding', null));

        $template = app(OnboardingTemplateService::class)->createTemplate(['name' => 'Standard', 'description' => null, 'employment_status_id' => null, 'is_active' => true, 'tasks' => [[
            'title' => 'Orientation', 'description' => null, 'category' => 'first_day', 'assignee_type' => 'hr', 'assignee_employee_id' => null,
            'due_relative_to' => 'start_date', 'due_offset_days' => 0, 'is_required' => true, 'employee_visible' => true,
            'requires_verification' => false, 'required_document_type_id' => null, 'is_active' => true,
        ]]], $this->dina);

        // A conversion id must belong to the employee.
        $this->actingAs($this->hrStaff)->post(route('people.onboarding.store'), [
            'employee_id' => $this->ramon->employee_id, 'onboarding_template_id' => $template->id, 'start_date' => '2026-11-02', 'application_conversion_id' => $conversion->id,
        ])->assertSessionHasErrors('application_conversion_id');

        $this->actingAs($this->hrStaff)->post(route('people.onboarding.store'), [
            'employee_id' => $conversion->employee_id, 'onboarding_template_id' => $template->id, 'start_date' => '2026-11-02', 'application_conversion_id' => $conversion->id,
        ])->assertSessionHasNoErrors();

        $onboarding = Onboarding::sole();
        $this->assertSame([$conversion->employee_id, $conversion->id], [$onboarding->employee_id, $onboarding->application_conversion_id]);
        $this->assertSame($after, $this->counts(), 'no second employee, number, movement or document');
        $this->actingAs($this->hrStaff)->get(route('recruitment.applications.show', $app))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('conversion.canStartOnboarding', false)->where('conversion.onboarding.id', $onboarding->id));
    }
}
