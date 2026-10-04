<?php

namespace Tests\Feature\Recruitment;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\ApplicantDocumentType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;

class ApplicantTest extends RecruitmentTestCase
{
    // ----------------------------------------------------------- create / update / validate

    #[Test]
    public function hr_creates_an_applicant_with_consent_education_and_work_experience(): void
    {
        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.store'), $this->applicantPayload([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'Maria.Santos@Example.TEST',
            'education' => [['level' => 'Bachelor', 'institute' => 'State University', 'degree' => 'BS Computer Science', 'start_date' => '2014-06-01', 'end_date' => '2018-04-30', 'is_completed' => true]],
            'work_experience' => [
                ['company' => 'Globex', 'job_title' => 'Developer', 'from' => '2018-06-01', 'to' => '2022-12-31', 'notes' => 'Built APIs.'],
                ['company' => 'Initech', 'job_title' => 'Senior Developer', 'from' => '2023-01-02', 'to' => null, 'notes' => 'Current role.'],
            ],
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $a = Applicant::sole();
        $this->assertSame(['APP-2026-00001', 'Maria Santos', 'maria.santos@example.test', true, 'active', $this->hrStaff->id],
            [$a->applicant_number, $a->full_name, $a->normalized_email, $a->privacy_consent, $a->status, $a->created_by]);
        $this->assertNotNull($a->privacy_consented_at);
        $this->assertSame(['State University', 'BS Computer Science', true], [$a->education[0]->institute, $a->education[0]->degree, $a->education[0]->is_completed]);
        $this->assertSame([['Globex', '2018-06-01', '2022-12-31', 'Built APIs.'], ['Initech', '2023-01-02', null, 'Current role.']],
            $a->workExperience()->orderBy('from')->get()->map(fn ($w) => [$w->company, $w->from->toDateString(), $w->to?->toDateString(), $w->notes])->all());
    }

    #[Test]
    public function consent_and_a_contact_method_are_required(): void
    {
        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.store'), ['first_name' => 'A', 'last_name' => 'B'])
            ->assertSessionHasErrors(['privacy_consent', 'email', 'phone']);

        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.store'), $this->applicantPayload(['email' => 'not-an-email', 'phone' => 'abc']))
            ->assertSessionHasErrors(['email', 'phone']);

        // Old employee-style names are not accepted for work experience.
        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.store'), $this->applicantPayload([
            'work_experience' => [['company' => 'Globex', 'job_title' => 'Dev', 'start_date' => '2020-01-01']],
        ]))->assertSessionHasErrors('work_experience.0.from');

        $this->assertSame(0, Applicant::count());
    }

    #[Test]
    public function an_applicant_can_be_updated_and_archived(): void
    {
        $a = $this->applicant();

        $this->actingAs($this->hrStaff)->put(route('recruitment.applicants.update', $a), $this->applicantPayload([
            'first_name' => 'Juana', 'last_name' => 'Cruz', 'email' => $a->email, 'phone' => $a->phone, 'status' => 'archived',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['Juana', 'archived'], [$a->fresh()->first_name, $a->fresh()->status]);
    }

    #[Test]
    public function internal_candidates_link_to_one_employee(): void
    {
        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.store'), $this->applicantPayload(['is_internal' => true]))
            ->assertSessionHasErrors('employee_id');

        $this->applicant(['is_internal' => true, 'employee_id' => $this->ramon->employee_id]);
        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.store'), $this->applicantPayload(['is_internal' => true, 'employee_id' => $this->ramon->employee_id]))
            ->assertSessionHasErrors('employee_id');

        // Not internal => no employee link is kept.
        $external = $this->applicant(['is_internal' => false, 'employee_id' => $this->maya->employee_id]);
        $this->assertNull($external->employee_id);
    }

    // ----------------------------------------------------------- duplicates

    #[Test]
    public function the_same_email_in_any_case_is_flagged_as_a_duplicate(): void
    {
        $existing = $this->applicant(['email' => 'John.Doe@example.com']);

        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.store'), $this->applicantPayload(['email' => '  john.doe@EXAMPLE.com ', 'phone' => null]))
            ->assertSessionHasErrors('duplicate');

        $this->assertSame(1, Applicant::count());
        $this->assertStringContainsString($existing->applicant_number, session('errors')->first('duplicate'));
    }

    #[Test]
    public function the_same_phone_in_another_format_is_flagged_as_a_duplicate(): void
    {
        $this->applicant(['email' => 'a@example.test', 'phone' => '0917-555-1234']);
        $this->applicant(['email' => 'b@example.test', 'phone' => '0918 000 0000', 'alternate_phone' => '(02) 8555-0199']);

        foreach (['+63 917 555 1234', '639175551234', '02 8555 0199'] as $phone) {
            $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.store'), $this->applicantPayload(['email' => null, 'phone' => $phone]))
                ->assertSessionHasErrors('duplicate');
        }
        $this->assertSame(2, Applicant::count());
    }

    #[Test]
    public function hr_can_confirm_a_different_person_and_edits_never_match_themselves(): void
    {
        $first = $this->applicant(['email' => 'shared@example.test']);

        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.store'), $this->applicantPayload(['email' => 'shared@example.test', 'confirm_not_duplicate' => true]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Applicant::where('normalized_email', 'shared@example.test')->count());

        // Updating an applicant with their own email/phone is not a duplicate of themselves...
        $solo = $this->applicant(['email' => 'solo@example.test', 'phone' => '0999 111 2222']);
        $this->actingAs($this->hrStaff)->put(route('recruitment.applicants.update', $solo), $this->applicantPayload(['email' => 'SOLO@example.test', 'phone' => '0999 111 2222', 'status' => 'active']))
            ->assertSessionHasNoErrors();

        // ...but changing to someone else's email is.
        $this->actingAs($this->hrStaff)->put(route('recruitment.applicants.update', $solo), $this->applicantPayload(['email' => $first->email, 'status' => 'active']))
            ->assertSessionHasErrors('duplicate');
    }

    // ----------------------------------------------------------- authorization

    #[Test]
    public function only_users_with_applicant_permissions_reach_the_registry(): void
    {
        $a = $this->applicant();

        foreach ([$this->ramon, $this->maya, $this->userWithRole('employee'), $this->userWithRole('payroll')] as $user) {
            $this->actingAs($user)->get(route('recruitment.applicants.index'))->assertForbidden();
            $this->actingAs($user)->get(route('recruitment.applicants.show', $a))->assertForbidden();
            $this->actingAs($user)->post(route('recruitment.applicants.store'), $this->applicantPayload())->assertForbidden();
            $this->actingAs($user)->put(route('recruitment.applicants.update', $a), $this->applicantPayload(['status' => 'active']))->assertForbidden();
        }

        $this->actingAs($this->hrStaff)->get(route('recruitment.applicants.show', $a))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('app/Recruitment/Applicants/Show', false)
                ->where('applicant.applicant_number', $a->applicant_number)
                ->missing('applicant.normalized_email')
                ->missing('applicant.phone_key'));
    }

    // ----------------------------------------------------------- documents

    #[Test]
    public function documents_are_stored_privately_and_served_only_to_authorized_users(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $a = $this->applicant();
        $resume = ApplicantDocumentType::where('code', 'resume')->firstOrFail();

        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.documents.store', $a), [
            'file' => UploadedFile::fake()->create('Juan CV.pdf', 200, 'application/pdf'),
            'applicant_document_type_id' => $resume->id,
        ])->assertSessionHasNoErrors();

        $doc = ApplicantDocument::sole();
        $this->assertStringStartsWith("recruitment/applicants/{$a->id}/", $doc->file_path);
        $this->assertStringNotContainsString('Juan CV', $doc->file_path);
        Storage::disk('local')->assertExists($doc->file_path);
        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->assertArrayNotHasKey('file_path', $doc->toArray());

        $download = $this->actingAs($this->hrStaff)->get(route('recruitment.applicants.documents.download', [$a, $doc]))->assertOk();
        $this->assertStringContainsString('Juan CV.pdf', $download->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('inline', $this->actingAs($this->hrStaff)->get(route('recruitment.applicants.documents.view', [$a, $doc]))->headers->get('Content-Disposition'));

        foreach ([$this->maya, $this->userWithRole('employee')] as $user) {
            $this->actingAs($user)->get(route('recruitment.applicants.documents.download', [$a, $doc]))->assertForbidden();
        }

        // Scoped to its applicant.
        $this->actingAs($this->hrStaff)->get(route('recruitment.applicants.documents.download', [$this->applicant(), $doc]))->assertNotFound();
    }

    #[Test]
    public function uploads_are_validated(): void
    {
        Storage::fake('local');
        $a = $this->applicant();
        $resume = ApplicantDocumentType::where('code', 'resume')->firstOrFail();

        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.documents.store', $a), ['file' => UploadedFile::fake()->create('x.exe', 5, 'application/x-msdownload'), 'applicant_document_type_id' => $resume->id])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.documents.store', $a), ['file' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'), 'applicant_document_type_id' => $resume->id])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.documents.store', $a), ['file' => UploadedFile::fake()->create('cv.pdf', 5, 'application/pdf')])
            ->assertSessionHasErrors('applicant_document_type_id');

        $this->assertSame(0, ApplicantDocument::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    #[Test]
    public function a_hiring_manager_sees_only_documents_submitted_to_their_vacancy_and_submitted_files_are_kept(): void
    {
        Storage::fake('local');
        $a = $this->applicant();
        $resume = ApplicantDocumentType::where('code', 'resume')->firstOrFail();
        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.documents.store', $a), ['file' => UploadedFile::fake()->create('cv.pdf', 5, 'application/pdf'), 'applicant_document_type_id' => $resume->id]);
        $this->actingAs($this->hrStaff)->post(route('recruitment.applicants.documents.store', $a), ['file' => UploadedFile::fake()->create('private.pdf', 5, 'application/pdf'), 'applicant_document_type_id' => $resume->id]);
        [$cv, $private] = ApplicantDocument::orderBy('id')->get()->all();

        $vacancy = $this->openVacancy(['hiring_manager_id' => $this->maya->employee_id]);
        $this->applyTo($vacancy, $a, ['document_ids' => [$cv->id]]);

        $this->actingAs($this->maya)->get(route('recruitment.applicants.documents.download', [$a, $cv]))->assertOk();
        $this->actingAs($this->maya)->get(route('recruitment.applicants.documents.download', [$a, $private]))->assertForbidden();

        // A submitted document is evidence and can't be deleted; an unsubmitted one can (with permission).
        $this->actingAs($this->dina)->delete(route('recruitment.applicants.documents.destroy', [$a, $cv]))->assertSessionHasErrors('document');
        $this->actingAs($this->dina)->delete(route('recruitment.applicants.documents.destroy', [$a, $private]))->assertSessionHasNoErrors();
        $this->assertSame([$cv->id], ApplicantDocument::pluck('id')->all());
        Storage::disk('local')->assertMissing($private->file_path);
    }
}
