<?php

namespace Tests\Feature\Careers;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Applicant;
use App\Models\ApplicantAccount;
use App\Models\ApplicantDocument;
use App\Models\ApplicantDocumentType;
use App\Models\Application;
use App\Models\JobOffer;
use App\Models\RecruitmentPortalEvent;
use App\Models\RecruitmentSource;
use App\Models\Vacancy;
use App\Models\VacancyStage;
use App\Notifications\Careers\ResetApplicantPassword;
use App\Services\Careers\CandidateStatusPresenter;
use App\Services\Careers\PublicVacancyService;
use App\Services\Recruitment\ApplicationPipelineService;
use App\Services\Recruitment\AssessmentService;
use App\Services\Recruitment\EvaluationService;
use App\Services\Recruitment\OfferApprovalService;
use App\Services\Recruitment\OfferService;
use App\Services\Recruitment\ScreeningService;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;

class CareersPortalTest extends CareersTestCase
{
    private function pdf(string $name = 'cv.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    private function apply(ApplicantAccount $account, Vacancy $vacancy, array $data = [])
    {
        return $this->asCandidate($account)->post(route('careers.jobs.apply.store', $vacancy->public_slug), $data + [
            'uploads' => [['applicant_document_type_id' => ApplicantDocumentType::where('code', 'resume')->value('id'), 'file' => $this->pdf()]],
            'cover_note' => 'I would love to join.',
        ]);
    }

    // ------------------------------------------------------------- A. public vacancies

    #[Test]
    public function only_published_open_external_vacancies_before_their_deadline_are_public(): void
    {
        $live = $this->published();
        $internalOnly = $this->openVacancy(['title' => 'Internal role', 'visibility' => 'internal']);
        $unpublished = $this->openVacancy(['title' => 'Not yet published']);
        $closed = $this->published(['title' => 'Closed role']);
        Vacancy::whereKey($closed->id)->update(['status' => Vacancy::STATUS_CLOSED]);
        $expired = $this->published(['title' => 'Expired role', 'closing_date' => '2026-10-03']);
        $onHold = $this->published(['title' => 'Paused role']);
        Vacancy::whereKey($onHold->id)->update(['status' => Vacancy::STATUS_ON_HOLD]);

        $this->get(route('careers.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('careers/Index', false)->has('jobs.data', 1)->where('jobs.data.0.slug', $live->public_slug));

        $this->get(route('careers.jobs.show', $live->public_slug))->assertOk();
        foreach ([$closed, $expired, $onHold] as $hidden) {
            $this->get(route('careers.jobs.show', $hidden->public_slug))->assertNotFound();
        }
        $this->get('/careers/jobs/'.$internalOnly->id)->assertNotFound();
        $this->get('/careers/jobs/does-not-exist-abc123')->assertNotFound();
        $this->assertNull($unpublished->public_slug);

        // The closing date itself still accepts applications; the day after it doesn't.
        Carbon::setTestNow('2026-10-31 23:00:00');
        $this->get(route('careers.jobs.show', $live->public_slug))->assertOk();
        Carbon::setTestNow('2026-11-01 00:01:00');
        $this->get(route('careers.jobs.show', $live->public_slug))->assertNotFound();
    }

    #[Test]
    public function the_public_detail_contains_public_fields_only(): void
    {
        $hidden = $this->published();
        $this->get(route('careers.jobs.show', $hidden->public_slug))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('careers/Job', false)
                ->where('job.title', 'Software Developer')->where('job.department', 'Information Technology')->where('job.salary', null)
                ->missing('job.id')->missing('job.hiring_manager_id')->missing('job.job_requisition_id')->missing('job.vacancy_number')
                ->missing('job.created_by')->missing('job.status_reason'));

        $shown = $this->published(['title' => 'Analyst'], showSalary: true);
        $this->get(route('careers.jobs.show', $shown->public_slug))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('job.salary.min', '50000.00')->where('job.salary.currency', 'PHP'));

        // Search and filters only ever see public vacancies.
        $this->get(route('careers.index', ['search' => 'analyst']))->assertInertia(fn (AssertableInertia $p) => $p->has('jobs.data', 1)->where('jobs.data.0.title', 'Analyst'));
        $this->get(route('careers.index', ['search' => 'Not yet published']))->assertInertia(fn (AssertableInertia $p) => $p->has('jobs.data', 0));
    }

    // ------------------------------------------------------------- B. registration & accounts

    #[Test]
    public function registration_needs_consent_and_activates_only_through_the_emailed_link(): void
    {
        $data = $this->registration(['email' => 'New.Person@Example.test']);

        $this->post(route('careers.register.store'), array_merge($data, ['privacy_consent' => false]))->assertSessionHasErrors('privacy_consent');
        $this->post(route('careers.register.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('careers.login'));

        $account = ApplicantAccount::sole();
        $this->assertSame(['new.person@example.test', null, null, null], [$account->email, $account->applicant_id, $account->password, $account->email_verified_at]);
        $this->assertSame(0, Applicant::count(), 'no applicant until the email is proven');

        // Can't sign in before completing; a tampered link is refused.
        $this->post(route('careers.login.store'), ['email' => $data['email'], 'password' => 'anything'])->assertSessionHasErrors('email');
        $url = $this->completionUrl($data['email']);
        $this->post($url.'x', ['password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123'])->assertForbidden();
        $this->post($url, ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');

        $this->post($url, ['password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123'])->assertRedirect(route('careers.applications.index'));
        $account->refresh();
        $applicant = Applicant::sole();
        $this->assertSame([$applicant->id, true], [$account->applicant_id, Hash::check('Secret-pass-123', $account->password)]);
        $this->assertNotNull($account->email_verified_at);
        $this->assertNull($account->getRawOriginal('pending_profile'));
        $this->assertSame(['Carla', 'new.person@example.test', true, null, 'Careers portal'], [$applicant->first_name, $applicant->normalized_email, $applicant->privacy_consent, $applicant->created_by, $applicant->source_details]);
        $this->assertNotNull($applicant->privacy_consented_at);
        $this->assertSame(['account_registered', 'applicant_created', 'account_activated'], RecruitmentPortalEvent::orderBy('id')->pluck('event')->all());

        // The link can't be reused.
        Auth::guard('applicant')->logout();
        $this->get($url)->assertRedirect(route('careers.login'));
        $this->post($url, ['password' => 'Other-pass-999', 'password_confirmation' => 'Other-pass-999'])->assertSessionHasErrors('password');

        $this->post(route('careers.login.store'), ['email' => 'NEW.person@example.test', 'password' => 'Secret-pass-123'])->assertRedirect(route('careers.applications.index'));
        $this->assertTrue(Auth::guard('applicant')->check());
        $this->assertFalse(Auth::guard('web')->check(), 'applicant login never signs in to the HRMS');
    }

    #[Test]
    public function an_hr_created_applicant_is_linked_by_email_not_duplicated_and_nothing_leaks(): void
    {
        $existing = $this->applicant(['email' => 'known@example.test', 'first_name' => 'Known']);
        $data = $this->registration(['email' => 'known@example.test', 'first_name' => 'Somebody']);

        $this->post(route('careers.register.store'), $data)->assertSessionHasNoErrors();
        $this->post($this->completionUrl('known@example.test'), ['password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123'])->assertSessionHasNoErrors();

        $this->assertSame($existing->id, ApplicantAccount::sole()->applicant_id);
        $this->assertSame(1, Applicant::count());
        $this->assertSame('Known', $existing->fresh()->first_name, 'HR data is not overwritten (no silent merge)');

        // Registering again with an active account's email: same generic answer, no new email, nothing changes.
        Notification::fake();
        $this->post(route('careers.register.store'), $data)->assertSessionHasNoErrors()->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    #[Test]
    public function a_phone_shared_with_another_applicant_is_referred_to_hr_without_revealing_them(): void
    {
        $this->applicant(['first_name' => 'Secret', 'last_name' => 'Person', 'phone' => '0917 111 2222']);
        $data = $this->registration(['phone' => '+63 917 111 2222']);
        $this->post(route('careers.register.store'), $data);

        $response = $this->post($this->completionUrl($data['email']), ['password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123'])
            ->assertSessionHasErrors(['password' => 'We could not set up your account automatically. Please contact our HR team.']);
        $this->assertStringNotContainsString('Secret', json_encode(session('errors')->getMessages()));
        $this->assertSame(1, Applicant::count());
        $this->assertNull(ApplicantAccount::sole()->applicant_id);
    }

    #[Test]
    public function password_reset_uses_the_applicant_broker_and_never_reveals_accounts(): void
    {
        $account = $this->candidate(['email' => 'reset.me@example.test']);

        $this->post(route('careers.password.email'), ['email' => 'nobody@example.test'])->assertSessionHas('status');
        $this->post(route('careers.password.email'), ['email' => 'reset.me@example.test'])->assertSessionHas('status');
        $token = null;
        Notification::assertSentTo($account, ResetApplicantPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post(route('careers.password.update'), ['token' => 'wrong', 'email' => 'reset.me@example.test', 'password' => 'New-secret-456', 'password_confirmation' => 'New-secret-456'])->assertSessionHasErrors('email');
        $this->post(route('careers.password.update'), ['token' => $token, 'email' => 'reset.me@example.test', 'password' => 'New-secret-456', 'password_confirmation' => 'New-secret-456'])->assertRedirect(route('careers.login'));
        $this->assertTrue(Hash::check('New-secret-456', $account->fresh()->password));
    }

    // ------------------------------------------------------------- C, F. applying

    #[Test]
    public function a_candidate_applies_into_the_existing_recruitment_pipeline(): void
    {
        $vacancy = $this->published();
        $account = $this->candidate();

        $this->asCandidate($account)->get(route('careers.jobs.apply', $vacancy->public_slug))->assertOk();
        $this->apply($account, $vacancy)->assertSessionHasNoErrors()->assertRedirect();

        $application = Application::sole();
        $this->assertSame(['APL-2026-00001', $account->applicant_id, $vacancy->id, 'active', $this->stage($vacancy, 'applied')->id, null],
            [$application->application_number, $application->applicant_id, $application->vacancy_id, $application->status, $application->current_vacancy_stage_id, $application->created_by]);
        $this->assertSame('company_website', RecruitmentSource::find($application->recruitment_source_id)->code);
        $this->assertSame(['applied'], $application->history->pluck('action')->all());
        $this->assertSame(0, $vacancy->fresh()->filled_count, 'applying never takes an opening');

        // HR sees it in the existing screens.
        $this->actingAs($this->hrStaff)->get(route('recruitment.applications.show', $application))->assertOk();
        $this->actingAs($this->hrStaff)->get(route('recruitment.applicants.show', $application->applicant_id))->assertOk();

        // The candidate sees a coarse status only.
        $this->asCandidate($account)->get(route('careers.applications.show', $application->application_number))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('careers/MyApplication', false)
                ->where('application.status.label', 'Application submitted')->has('application.documents', 1)
                ->missing('application.id')->missing('application.current_vacancy_stage_id')->missing('application.history'));
    }

    #[Test]
    public function the_same_position_can_be_applied_to_once_and_closed_positions_not_at_all(): void
    {
        $vacancy = $this->published();
        $account = $this->candidate();
        $this->apply($account, $vacancy)->assertSessionHasNoErrors();
        $this->apply($account, $vacancy)->assertSessionHasErrors(['applicant_id' => 'This applicant has already applied to this vacancy.']);

        $closed = $this->published(['title' => 'Closing today']);
        Vacancy::whereKey($closed->id)->update(['status' => Vacancy::STATUS_CLOSED]);
        $this->apply($account, $closed)->assertSessionHasErrors(['vacancy_id' => 'This position is no longer accepting applications.']);
        $unpublished = $this->published(['title' => 'Pulled']);
        app(PublicVacancyService::class)->unpublish($unpublished, $this->hrStaff);
        $this->apply($account, $unpublished->fresh())->assertSessionHasErrors('vacancy_id');

        $this->assertSame(1, Application::count());
        $this->assertSame(1, ApplicantDocument::count(), 'failed attempts leave no documents');
        $this->assertCount(1, Storage::disk('local')->allFiles('recruitment'));
    }

    #[Test]
    public function required_document_types_are_enforced_and_uploads_are_validated(): void
    {
        $vacancy = $this->published();
        $account = $this->candidate();
        ApplicantDocumentType::where('code', 'certificate')->update(['required_online' => true]);
        $resume = ApplicantDocumentType::where('code', 'resume')->value('id');
        $cert = ApplicantDocumentType::where('code', 'certificate')->value('id');

        $this->apply($account, $vacancy)->assertSessionHasErrors(['uploads' => 'Please include: Certificate.']);
        $this->apply($account, $vacancy, ['uploads' => [['applicant_document_type_id' => $resume, 'file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')]]])->assertSessionHasErrors('uploads.0.file');
        // A real file (not a test fake, which trusts the name): an executable renamed to .pdf is caught by content type.
        $exe = tempnam(sys_get_temp_dir(), 'exe');
        file_put_contents($exe, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff\x00\x00".str_repeat("\x00", 64)."PE\x00\x00");
        $this->apply($account, $vacancy, ['uploads' => [['applicant_document_type_id' => $resume, 'file' => new UploadedFile($exe, 'fake.pdf', 'application/pdf', null, true)]]])->assertSessionHasErrors('uploads.0.file');
        $this->apply($account, $vacancy, ['uploads' => [['applicant_document_type_id' => $resume, 'file' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')]]])->assertSessionHasErrors('uploads.0.file');
        $this->assertSame(0, Application::count());

        $this->apply($account, $vacancy, ['uploads' => [
            ['applicant_document_type_id' => $resume, 'file' => $this->pdf()],
            ['applicant_document_type_id' => $cert, 'file' => $this->pdf('cert.pdf')],
        ]])->assertSessionHasNoErrors();

        $doc = ApplicantDocument::where('original_name', 'cert.pdf')->sole();
        Storage::disk('local')->assertExists($doc->file_path);
        $this->assertStringNotContainsString('cert', $doc->file_path);
        $this->assertSame(2, Application::sole()->documents()->count());
    }

    // ------------------------------------------------------------- D, E. documents & ownership

    #[Test]
    public function candidates_reach_only_their_own_applications_and_documents(): void
    {
        $vacancy = $this->published();
        $alice = $this->candidate(['first_name' => 'Alice']);
        $bob = $this->candidate(['first_name' => 'Bob']);
        $this->apply($alice, $vacancy)->assertSessionHasNoErrors();
        $aliceApp = Application::sole();
        $aliceDoc = ApplicantDocument::sole();

        $this->asCandidate($alice)->get(route('careers.documents.download', $aliceDoc->id))->assertOk();
        $this->asCandidate($bob)->get(route('careers.documents.download', $aliceDoc->id))->assertNotFound();
        $this->asCandidate($bob)->get(route('careers.applications.show', $aliceApp->application_number))->assertNotFound();
        $this->asCandidate($bob)->post(route('careers.applications.withdraw', $aliceApp->application_number), ['reason' => 'x'])->assertNotFound();
        $this->asCandidate($bob)->delete(route('careers.documents.destroy', $aliceDoc->id))->assertNotFound();
        $this->asCandidate($bob)->post(route('careers.jobs.apply.store', $vacancy->public_slug), ['document_ids' => [$aliceDoc->id]])->assertSessionHasErrors('document_ids');
        $this->asCandidate($bob)->get(route('careers.applications.index'))->assertInertia(fn (AssertableInertia $p) => $p->has('applications', 0));

        // A submitted document can't be deleted; an unused one can.
        $this->asCandidate($alice)->delete(route('careers.documents.destroy', $aliceDoc->id))->assertSessionHasErrors('document');
        $this->asCandidate($alice)->post(route('careers.documents.store'), ['applicant_document_type_id' => ApplicantDocumentType::where('code', 'portfolio')->value('id'), 'file' => $this->pdf('folio.pdf')])->assertSessionHasNoErrors();
        $folio = ApplicantDocument::where('original_name', 'folio.pdf')->sole();
        $this->asCandidate($alice)->delete(route('careers.documents.destroy', $folio->id))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($folio->file_path);
    }

    #[Test]
    public function the_candidate_status_never_reveals_internal_workflow_or_reasons(): void
    {
        $vacancy = $this->published();
        $account = $this->candidate();
        $this->apply($account, $vacancy);
        $app = Application::sole();
        $pipeline = app(ApplicationPipelineService::class);
        $status = fn () => $this->asCandidate($account)->get(route('careers.applications.show', $app->application_number))->viewData('page')['props']['application']['status']['label'];

        $pipeline->advance($app, $app->current_vacancy_stage_id, $this->hrStaff);
        $this->assertSame('Under review', $status());

        $pipeline->reject($app->fresh(), $this->reason('insufficient_experience')->id, $this->hrStaff, 'Internal: weak PHP answers');
        $page = $this->asCandidate($account)->get(route('careers.applications.show', $app->application_number));
        $page->assertInertia(fn (AssertableInertia $p) => $p->where('application.status.label', 'Application closed')->where('application.can_withdraw', false));
        $this->assertStringNotContainsString('weak PHP', $page->getContent());
        $this->assertStringNotContainsString('Insufficient experience', $page->getContent());
    }

    #[Test]
    public function the_candidate_tracker_follows_each_stage_without_internal_details(): void
    {
        $vacancy = $this->published();
        $account = $this->candidate();
        $this->apply($account, $vacancy);
        $app = Application::sole();
        $pipeline = app(ApplicationPipelineService::class);
        $page = fn () => $this->asCandidate($account)->get(route('careers.applications.show', $app->application_number));
        $label = fn () => $page()->viewData('page')['props']['application']['status']['label'];

        $this->assertSame('Application submitted', $label());
        $pipeline->advance($app, $app->current_vacancy_stage_id, $this->hrStaff);
        $this->assertSame('Under review', $label()); // Screening
        app(ScreeningService::class)->record($app, ['result' => 'passed', 'screened_on' => '2026-10-04', 'remarks' => 'Internal: strong CV'], $this->hrStaff);
        $app = $pipeline->shortlist($app, $app->fresh()->current_vacancy_stage_id, $this->hrStaff)->fresh();
        $this->assertSame('Under review', $label()); // Shortlisted
        $app = $this->moveOn($app);
        $this->assertSame('Interview', $label());
        $interview = $this->completedInterview($app, [$this->maya]);
        app(EvaluationService::class)->submit($interview, ['recommendation' => 'do_not_recommend', 'comments' => 'Internal: panel concerns', 'scores' => $this->scores(2)], $this->maya);
        $app = $this->moveOn($app);
        $this->assertSame('Assessment', $label());
        $assessment = app(AssessmentService::class)->create($app, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);
        app(AssessmentService::class)->complete($assessment, ['score' => 61, 'maximum_score' => 97], $this->hrStaff);
        $app = $this->moveOn($app);

        $response = $page();
        $response->assertInertia(fn (AssertableInertia $p) => $p->where('application.status.label', 'Evaluation')
            ->where('application.status.key', 'evaluation')
            ->where('application.status.step', 5)
            ->where('application.steps', ['Submitted', 'Under review', 'Interview', 'Assessment', 'Evaluation', 'Offer']));
        $props = json_encode($response->viewData('page')['props']); // what the candidate's page receives
        foreach (['Maya', 'Manager', 'panel concerns', 'do_not_recommend', 'Do not recommend', 'strong CV', 'score', 'Approval'] as $internal) {
            $this->assertStringNotContainsString($internal, $props, $internal);
        }

        // A selection and a draft offer are still "Evaluation"; the offer shows once issued.
        $this->select($app);
        $offer = $this->draftOffer($app, ['expiry_date' => '2026-10-30']);
        $this->assertSame('Evaluation', $label());
        $manager = $this->hrApprovalChain();
        $offer = app(OfferService::class)->submit($offer, null, $this->hrStaff);
        app(OfferApprovalService::class)->approve($offer, $manager);
        $offer = app(OfferApprovalService::class)->approve($offer, $this->dina);
        $this->assertSame('Evaluation', $label());
        $this->assertStringNotContainsString('pproval', json_encode($page()->viewData('page')['props'])); // HR approval details never reach the candidate
        $offer = app(OfferService::class)->issue($offer, null, $this->hrStaff);
        $this->assertSame('Offer available', $label());
        app(OfferService::class)->respond($offer, 'accepted', null, $this->hrStaff);
        $this->assertSame('Offer accepted', $label());
    }

    #[Test]
    public function the_candidate_status_mapping_covers_every_outcome(): void
    {
        $presenter = app(CandidateStatusPresenter::class);
        $label = function (string $status, ?string $stage = null, ?string $offer = null, bool $converted = false) use ($presenter) {
            $application = new Application(['status' => $status]);
            $application->setRelation('currentStage', $stage ? new VacancyStage(['stage_type' => $stage]) : null);

            return $presenter->present($application, $offer ? new JobOffer(['status' => $offer]) : null, $converted)['label'];
        };

        $this->assertSame('Application submitted', $label('active', 'applied'));
        $this->assertSame('Under review', $label('active', 'screening'));
        $this->assertSame('Under review', $label('shortlisted', 'shortlisted'));
        $this->assertSame('Interview', $label('shortlisted', 'interview'));
        $this->assertSame('Assessment', $label('shortlisted', 'assessment'));
        $this->assertSame('Evaluation', $label('shortlisted', 'evaluation'));
        foreach (['draft', 'pending_approval', 'approved', 'rejected', 'withdrawn'] as $notVisible) {
            $this->assertSame('Evaluation', $label('shortlisted', 'evaluation', $notVisible), $notVisible);
        }
        $this->assertSame('Offer available', $label('shortlisted', 'evaluation', 'issued'));
        $this->assertSame('Offer accepted', $label('shortlisted', 'evaluation', 'accepted'));
        $this->assertSame('Offer accepted', $label('shortlisted', 'evaluation', 'accepted', converted: true));
        $this->assertSame('Application closed', $label('shortlisted', 'evaluation', 'declined'));
        $this->assertSame('Application closed', $label('shortlisted', 'evaluation', 'expired'));
        $this->assertSame('Application closed', $label('rejected', 'screening'));
        $this->assertSame('Application withdrawn', $label('withdrawn', 'interview'));
    }

    // ------------------------------------------------------------- G. withdrawal

    #[Test]
    public function the_candidate_withdraws_through_the_pipeline_service(): void
    {
        $vacancy = $this->published();
        $account = $this->candidate();
        $this->apply($account, $vacancy);
        $app = Application::sole();

        $this->asCandidate($account)->post(route('careers.applications.withdraw', $app->application_number), [])->assertSessionHasErrors('reason');
        $this->asCandidate($account)->post(route('careers.applications.withdraw', $app->application_number), ['reason' => 'Accepted another job'])->assertSessionHasNoErrors();

        $app->refresh();
        $this->assertSame(['withdrawn', null], [$app->status, $app->withdrawn_by]);
        $this->assertSame(['applied', 'withdrawn'], $app->history->pluck('action')->all());
        $this->assertStringContainsString('Accepted another job', $app->history->last()->remarks);
        $this->asCandidate($account)->post(route('careers.applications.withdraw', $app->application_number), ['reason' => 'again'])->assertSessionHasErrors('status');
        $this->assertSame(1, RecruitmentPortalEvent::where('event', 'application_withdrawn')->count());
    }

    #[Test]
    public function withdrawal_is_refused_online_once_an_offer_is_in_play(): void
    {
        $vacancy = $this->published();
        $account = $this->candidate();
        $this->apply($account, $vacancy);
        $app = Application::sole();
        // Take the application to an offer through the existing services.
        $pipeline = app(ApplicationPipelineService::class);
        $pipeline->advance($app, $app->current_vacancy_stage_id, $this->hrStaff);
        app(ScreeningService::class)->record($app, ['result' => 'passed', 'screened_on' => '2026-10-04'], $this->hrStaff);
        $app = $pipeline->shortlist($app, $app->fresh()->current_vacancy_stage_id, $this->hrStaff)->fresh();
        $app = $this->moveOn($app);
        $interview = $this->completedInterview($app, [$this->maya]);
        app(EvaluationService::class)->submit($interview, ['recommendation' => 'recommend', 'scores' => $this->scores()], $this->maya);
        $app = $this->moveOn($app);
        $assessment = app(AssessmentService::class)->create($app, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);
        app(AssessmentService::class)->complete($assessment, ['score' => 9, 'maximum_score' => 10], $this->hrStaff);
        $app = $this->moveOn($app);
        $this->select($app);
        $this->draftOffer($app, ['expiry_date' => '2026-10-30']);

        $this->asCandidate($account)->post(route('careers.applications.withdraw', $app->application_number), ['reason' => 'x'])
            ->assertSessionHasErrors(['status' => 'This application can no longer be withdrawn online. Please contact our HR team.']);
        $this->asCandidate($account)->get(route('careers.applications.show', $app->application_number))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('application.status.label', 'Evaluation')->where('application.can_withdraw', false));
        $this->assertSame('shortlisted', $app->fresh()->status);
    }

    // ------------------------------------------------------------- H. publication

    #[Test]
    public function hr_publishes_unpublishes_and_previews_with_the_publish_permission(): void
    {
        $vacancy = $this->openVacancy(['title' => 'Data Engineer']);
        $internal = $this->openVacancy(['title' => 'Internal', 'visibility' => 'internal']);

        $this->actingAs($this->outsider)->post(route('recruitment.vacancies.publish-online', $vacancy))->assertForbidden();
        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.publish-online', $internal))->assertSessionHasErrors('publish');
        $this->actingAs($this->hrStaff)->get(route('recruitment.vacancies.public-preview', $vacancy))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('preview', true)->where('job.title', 'Data Engineer'));

        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.publish-online', $vacancy))->assertSessionHasNoErrors();
        $slug = $vacancy->fresh()->public_slug;
        $this->assertMatchesRegularExpression('/^data-engineer-[a-z0-9]{6}$/', $slug);
        $this->get(route('careers.jobs.show', $slug))->assertOk();

        // The slug survives a title change and an unpublish/republish.
        $vacancy->update(['title' => 'Senior Data Engineer']);
        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.unpublish-online', $vacancy))->assertSessionHasNoErrors();
        $this->get(route('careers.jobs.show', $slug))->assertNotFound();
        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.publish-online', $vacancy), ['show_salary_publicly' => true])->assertSessionHasNoErrors();
        $this->assertSame([$slug, true], [$vacancy->fresh()->public_slug, $vacancy->fresh()->show_salary_publicly]);

        $this->assertSame(['vacancy_published', 'vacancy_unpublished', 'vacancy_published'], RecruitmentPortalEvent::where('vacancy_id', $vacancy->id)->orderBy('id')->pluck('event')->all());
        $this->actingAs($this->hrStaff)->get(route('recruitment.vacancies.show', $vacancy))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('publication.live', true)->where('publication.url', route('careers.jobs.show', $slug)));
    }

    // ------------------------------------------------------------- security

    #[Test]
    public function applicants_never_reach_internal_routes_and_cannot_set_internal_fields(): void
    {
        $vacancy = $this->published();
        $account = $this->candidate();
        $this->apply($account, $vacancy, ['status' => 'shortlisted', 'current_vacancy_stage_id' => 999, 'employee_id' => 1, 'applicant_id' => 999, 'created_by' => 1])->assertSessionHasNoErrors();
        $app = Application::sole();
        $this->assertSame(['active', $this->stage($vacancy, 'applied')->id, $account->applicant_id, null], [$app->status, $app->current_vacancy_stage_id, $app->applicant_id, $app->created_by]);

        // Internal recruitment and HR routes treat a candidate as a guest.
        foreach ([route('recruitment.applications.index'), route('recruitment.applicants.index'), route('recruitment.applications.show', $app), route('people.onboarding.index'), route('dashboard.index')] as $url) {
            $response = $this->asCandidate($account)->get($url);
            $this->assertTrue($response->isRedirect() || $response->status() === 403, $url);
            $this->assertFalse($response->isOk(), $url);
        }
        $this->asCandidate($account)->post(route('recruitment.applications.advance', $app), ['expected_stage_id' => $app->current_vacancy_stage_id])->assertRedirect();
        $this->assertSame('active', $app->fresh()->status);
        $this->assertSame([], $account->getAttributes()['roles'] ?? []);

        // Guests can't reach candidate pages.
        Auth::guard('applicant')->logout();
        $this->get(route('careers.applications.index'))->assertRedirect(route('careers.login'));
        $this->get(route('careers.jobs.apply', $vacancy->public_slug))->assertRedirect(route('careers.login'));
    }

    #[Test]
    public function a_disabled_account_is_signed_out(): void
    {
        $account = $this->candidate();
        $account->update(['status' => ApplicantAccount::STATUS_DISABLED]);
        $this->asCandidate($account)->get(route('careers.applications.index'))->assertRedirect(route('careers.login'));
        $this->post(route('careers.login.store'), ['email' => $account->email, 'password' => 'Secret-pass-123'])->assertSessionHasErrors('email');
    }

    #[Test]
    public function profile_updates_keep_identity_fields_and_internal_links(): void
    {
        $employee = $this->employee($this->outsider);
        $internal = $this->applicant(['email' => 'inside@example.test', 'is_internal' => true, 'employee_id' => $employee->id]);
        $this->post(route('careers.register.store'), $this->registration(['email' => 'inside@example.test']));
        $this->post($this->completionUrl('inside@example.test'), ['password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123']);
        $account = ApplicantAccount::where('email', 'inside@example.test')->sole();

        $this->asCandidate($account)->put(route('careers.profile.update'), [
            'phone' => '0917 999 0000', 'address' => 'Taguig', 'email' => 'hijack@example.test', 'first_name' => 'Changed', 'employee_id' => null, 'is_internal' => false,
            'education' => [['institute' => 'State U', 'degree' => 'BSIT']],
            'work_experience' => [['company' => 'Acme', 'job_title' => 'Dev', 'from' => '2020-01-01', 'to' => '2022-01-01']],
        ])->assertSessionHasNoErrors();

        $internal->refresh();
        $this->assertSame(['0917 999 0000', 'Taguig', 'inside@example.test', $internal->first_name, $employee->id, true],
            [$internal->phone, $internal->address, $internal->email, $internal->first_name, $internal->employee_id, $internal->is_internal]);
        $this->assertSame([1, 1], [$internal->education()->count(), $internal->workExperience()->count()]);
    }

    #[Test]
    public function public_routes_use_csrf_rate_limits_and_the_separate_guard(): void
    {
        $route = Route::getRoutes()->getByName('careers.jobs.apply.store');
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains(VerifyCsrfToken::class, app(HttpKernel::class)->getMiddlewareGroups()['web']);
        $this->assertContains('throttle:careers-apply', $route->gatherMiddleware());
        $this->assertContains('throttle:careers-auth', Route::getRoutes()->getByName('careers.login.store')->gatherMiddleware());
        $this->assertNotContains('auth', Route::getRoutes()->getByName('careers.index')->gatherMiddleware());
        $this->assertSame('applicant_accounts', config('auth.guards.applicant.provider'));
        $this->assertSame('users', config('auth.guards.web.provider'), 'employee guard unchanged');

        // Login throttling kicks in.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('careers.login.store'), ['email' => 'brute@example.test', 'password' => 'nope'.$i]);
        }
        $this->post(route('careers.login.store'), ['email' => 'brute@example.test', 'password' => 'nope'])->assertStatus(429);
    }
}
