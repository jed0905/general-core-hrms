<?php

namespace Database\Seeders\Demo;

use App\Models\Applicant;
use App\Models\ApplicantAccount;
use App\Models\Application;
use App\Models\ApplicationAssessment;
use App\Models\ApplicationInterview;
use App\Models\ApplicationSelection;
use App\Models\CareersSetting;
use App\Models\Department;
use App\Models\EmploymentStatus;
use App\Models\EvaluationCriterion;
use App\Models\JobOffer;
use App\Models\JobRequisition;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\Vacancy;
use App\Services\Careers\ApplicantAccountService;
use App\Services\Careers\CareersApplicationService;
use App\Services\Careers\PublicVacancyService;
use App\Services\EmployeeDocumentService;
use App\Services\Recruitment\ApplicantDocumentService;
use App\Services\Recruitment\ApplicantService;
use App\Services\Recruitment\ApplicationConversionService;
use App\Services\Recruitment\ApplicationPipelineService;
use App\Services\Recruitment\ApplicationService;
use App\Services\Recruitment\AssessmentService;
use App\Services\Recruitment\EvaluationService;
use App\Services\Recruitment\InterviewService;
use App\Services\Recruitment\OfferApprovalService;
use App\Services\Recruitment\OfferService;
use App\Services\Recruitment\RequisitionApprovalService;
use App\Services\Recruitment\RequisitionService;
use App\Services\Recruitment\ScreeningService;
use App\Services\Recruitment\SelectionService;
use App\Services\Recruitment\VacancyService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recruitment part of the Acme demo (phases 1–8): requisitions and their
 * approvals, vacancies, applicants (HR-entered, careers-portal and internal),
 * the whole pipeline, offers with approvals, and a conversion to employee.
 *
 * Every record goes through the recruitment services, so business rules,
 * numbering, histories, events and openings stay consistent. Events are
 * recorded on their own dates, relative to the day the seeder runs, by
 * moving the clock. All applicants are fictional (example.com addresses).
 *
 * Scenarios (T = today):
 * - Sales Executive: closed vacancy (opened T-115, closed T-81).
 * - Accountant: filled; one hire converted to an employee (start T-40).
 * - HR Specialist: open, 2 openings; offers declined, accepted, issued.
 * - IT Support Specialist: put on hold, then cancelled.
 * - Customer Support Representative: open, 4 openings; offers rejected in
 *   approval, pending, approved, expired and withdrawn.
 * - Senior Software Developer: the main active vacancy, every stage.
 * - One requisition awaiting approval and one rejected.
 */
trait SeedsAcmeRecruitment
{
    /** First demo applicant: its presence means the recruitment demo was seeded. */
    private const RECRUITMENT_MARKER = 'maria.reyes@example.com';

    private Carbon $today;

    private array $ids = [];

    /** @var array<string, Vacancy> */
    private array $vacancies = [];

    /** @var array<string, Application> */
    private array $applications = [];

    /** @var array<string, ApplicantAccount> */
    private array $portalAccounts = [];

    private array $tempFiles = [];

    private int $applicantSeq = 0;

    private function recruitment(): void
    {
        Carbon::setTestNow();
        $this->today = now()->startOfDay();
        $this->ids = [
            'source' => DB::table('recruitment_sources')->pluck('id', 'code')->all(),
            'reason' => DB::table('rejection_reasons')->pluck('id', 'code')->all(),
            'interview' => DB::table('interview_types')->pluck('id', 'code')->all(),
            'assessment' => DB::table('assessment_types')->pluck('id', 'code')->all(),
            'document' => DB::table('applicant_document_types')->pluck('id', 'code')->all(),
            'criteria' => EvaluationCriterion::active()->pluck('id')->all(),
        ];

        $disk = Storage::disk('local');
        $filesBefore = array_merge($disk->allFiles(ApplicantDocumentService::DIRECTORY), $disk->allFiles(EmployeeDocumentService::DIRECTORY));
        $mailer = config('mail.default');
        // Portal registration emails a sign-up link; demo addresses must never receive mail.
        config(['mail.default' => 'array']);
        // Activating a portal account signs the candidate in, which needs a session (none in the console).
        request()->setLaravelSession(app('session')->driver('array'));

        try {
            DB::transaction(function () {
                $this->careersSite();
                $this->salesExecutiveClosed();
                $this->accountantFilled();
                $this->hrSpecialistOffers();
                $this->itSupportCancelled();
                $this->customerSupportOpenings();
                $this->seniorDeveloperActive();
                $this->otherRequisitions();
                $this->unfinishedRegistration();
            });
        } catch (Throwable $e) {
            // The rows were rolled back; remove the files this run stored.
            $after = array_merge($disk->allFiles(ApplicantDocumentService::DIRECTORY), $disk->allFiles(EmployeeDocumentService::DIRECTORY));
            $disk->delete(array_values(array_diff($after, $filesBefore)));
            throw $e;
        } finally {
            Auth::forgetGuards();
            config(['mail.default' => $mailer]);
            foreach ($this->tempFiles as $file) {
                @unlink($file);
            }
            Carbon::setTestNow();
        }

        $this->command?->info(sprintf('Acme recruitment demo created: %d vacancies, %d applications, %d careers accounts.',
            count($this->vacancies), count($this->applications), count($this->portalAccounts) + 1));
    }

    // ------------------------------------------------------------------
    // Clock and lookups
    // ------------------------------------------------------------------

    /** Move the clock to T-$daysAgo at $time; returns that moment. */
    private function on(int $daysAgo, string $time = '09:00'): Carbon
    {
        $at = $this->today->copy()->subDays($daysAgo)->setTimeFromTimeString($time);
        Carbon::setTestNow($at);

        return $at;
    }

    /** A date relative to today (negative = in the future), without moving the clock. */
    private function day(int $daysAgo): string
    {
        return $this->today->copy()->subDays($daysAgo)->toDateString();
    }

    private function emp(string $key): int
    {
        return $this->employees[$key]->id;
    }

    private function deptId(string $shortcut): int
    {
        return (int) Department::where('shortcut', $shortcut)->orderBy('id')->value('id');
    }

    private function titleId(string $title): int
    {
        return (int) JobTitle::where('job_title', $title)->orderBy('id')->value('id');
    }

    private function statusId(string $name): int
    {
        return (int) EmploymentStatus::where('name', $name)->orderBy('id')->value('id');
    }

    private function locationId(string $prefix): int
    {
        return (int) Location::where('address', 'like', "{$prefix}%")->orderBy('id')->value('id');
    }

    private function fresh(string $key): Application
    {
        return $this->applications[$key] = $this->applications[$key]->fresh();
    }

    // ------------------------------------------------------------------
    // Careers site, requisitions, vacancies
    // ------------------------------------------------------------------

    private function careersSite(): void
    {
        $settings = CareersSetting::current();

        if ($settings->updated_by === null) { // still the installation defaults
            $settings->update([
                'headline' => 'Build what\'s next with Acme Digital',
                'introduction' => 'Acme Digital Solutions is growing. Browse our open roles in Pasig, Quezon City, Taguig and remote, and apply online in a few minutes.',
                'updated_by' => $this->users['hrm']->id,
            ]);
        }
    }

    /**
     * Requested by $requester, approved through the requisition workflow by $approvers (in order).
     */
    private function requisition(string $requester, array $data, int $createdDaysAgo, array $approvers): JobRequisition
    {
        $this->on($createdDaysAgo, '10:00');
        $service = app(RequisitionService::class);
        $requisition = $service->createRequisition($data + ['requested_by_employee_id' => $this->emp($requester)], $this->users[$requester]);
        $this->on($createdDaysAgo, '10:30');
        $requisition = $service->submit($requisition, $this->users[$requester]);

        foreach ($approvers as $i => [$approver, $remarks]) {
            $this->on($createdDaysAgo - 1 - $i, '14:00');
            $requisition = app(RequisitionApprovalService::class)->approve($requisition, $this->users[$approver], $remarks);
        }

        return $requisition;
    }

    private function openVacancy(string $key, array $data, int $openedDaysAgo, bool $publish): Vacancy
    {
        $service = app(VacancyService::class);
        $this->on($openedDaysAgo + 1, '15:00');
        $vacancy = $service->createVacancy($data, $this->users['hro']);
        $this->on($openedDaysAgo, '09:00');
        $vacancy = $service->transition($vacancy, Vacancy::STATUS_OPEN, $this->users['hro']);

        if ($publish) {
            $this->on($openedDaysAgo, '09:15');
            $vacancy = app(PublicVacancyService::class)->publish($vacancy, $this->users['hro']);
        }

        return $this->vacancies[$key] = $vacancy->fresh();
    }

    // ------------------------------------------------------------------
    // Applicants and applications
    // ------------------------------------------------------------------

    /**
     * A fictional candidate: [first, last, city, school, degree, company, role, years of experience].
     */
    private function profile(array $person, ?string $source = null, ?string $details = null): array
    {
        [$first, $last, $city, $school, $degree, $company, $role, $years] = $person;
        $n = ++$this->applicantSeq;
        $graduated = 2025 - $years;

        return [
            'first_name' => $first,
            'last_name' => $last,
            'email' => Str::slug("{$first} {$last}", '.').'@example.com',
            'phone' => sprintf('0918 600 %04d', 1000 + $n),
            'address' => sprintf('%d Sampaguita Street, Barangay %d, %s', 10 + $n * 3, 200 + $n, $city),
            'recruitment_source_id' => $source ? $this->ids['source'][$source] : null,
            'source_details' => $details,
            'education' => [[
                'level' => 'College', 'institute' => $school, 'degree' => $degree,
                'start_date' => ($graduated - 4).'-06-01', 'end_date' => $graduated.'-04-30', 'is_completed' => true,
            ]],
            'work_experience' => $years > 0 ? [[
                'company' => $company, 'job_title' => $role, 'from' => $graduated.'-06-01', 'to' => null,
                'notes' => "{$role} at a fictional company (demo data).",
            ]] : [],
        ];
    }

    /**
     * HR records an applicant and their application (optionally with a résumé).
     */
    private function hrApplication(string $key, string $vacancy, array $person, string $source, int $daysAgo, string $time = '10:00', ?string $details = null, bool $resume = false): Application
    {
        $this->on($daysAgo, $time);
        $profile = $this->profile($person, $source, $details);
        $applicant = app(ApplicantService::class)->createApplicant($profile, $this->users['hro']);
        $documents = $resume ? [$this->resume($applicant, $this->users['hro'])->id] : [];

        return $this->applications[$key] = app(ApplicationService::class)->createApplication($applicant, $this->vacancies[$vacancy], [
            'recruitment_source_id' => $this->ids['source'][$source],
            'document_ids' => $documents,
            'remarks' => 'Application recorded by HR.',
        ], $this->users['hro']);
    }

    /**
     * An existing Acme employee applies internally (linked to their employee record).
     */
    private function internalApplication(string $key, string $vacancy, string $employee, int $daysAgo): Application
    {
        $this->on($daysAgo, '11:00');
        $e = $this->employees[$employee];
        $applicant = app(ApplicantService::class)->createApplicant([
            'first_name' => $e->emp_first_name,
            'middle_name' => $e->emp_middle_name,
            'last_name' => $e->emp_last_name,
            'email' => $e->work_email,
            'phone' => $e->mobile_no,
            'recruitment_source_id' => $this->ids['source']['internal'],
            'source_details' => 'Internal job posting',
            'is_internal' => true,
            'employee_id' => $e->id,
        ], $this->users['hro']);

        return $this->applications[$key] = app(ApplicationService::class)->createApplication($applicant, $this->vacancies[$vacancy], [
            'recruitment_source_id' => $this->ids['source']['internal'],
            'remarks' => 'Internal application.',
        ], $this->users['hro']);
    }

    /**
     * The candidate registers on the careers site, activates the account,
     * completes their profile and applies online with a résumé.
     */
    private function portalApplication(string $key, string $vacancy, array $person, int $daysAgo): Application
    {
        $profile = $this->profile($person);
        $accounts = app(ApplicantAccountService::class);
        $careers = app(CareersApplicationService::class);

        $this->on($daysAgo, '19:10');
        $accounts->register(array_intersect_key($profile, array_flip(['first_name', 'last_name', 'email', 'phone', 'address'])));
        $account = ApplicantAccount::where('email', $profile['email'])->firstOrFail();

        $this->on($daysAgo, '19:25');
        $account = $accounts->complete($account, $this->portalPassword());
        Auth::guard('applicant')->logout();

        $this->on($daysAgo, '19:40');
        $careers->updateProfile($account, Arr::only($profile, ['education', 'work_experience', 'address']));

        $this->on($daysAgo, '20:05');
        $application = $careers->submit($account->fresh(), $this->vacancies[$vacancy]->public_slug, [
            'uploads' => [['applicant_document_type_id' => $this->ids['document']['resume'], 'file' => $this->demoPdf("{$profile['first_name']} {$profile['last_name']} - Resume", $this->resumeLines($profile))]],
            'cover_note' => 'I would love to join the Acme team.',
        ]);

        $this->portalAccounts[$key] = $account->fresh();

        return $this->applications[$key] = $application;
    }

    private function portalPassword(): string
    {
        return $this->password !== '' ? $this->password : ($this->password = 'Acme-'.Str::password(10, symbols: false).'!');
    }

    /** A candidate who registered but never followed the activation link. */
    private function unfinishedRegistration(): void
    {
        $this->on(3, '21:30');
        app(ApplicantAccountService::class)->register([
            'first_name' => 'Lara', 'last_name' => 'Villareal', 'email' => 'lara.villareal@example.com', 'phone' => '0918 600 1999',
        ]);
    }

    // ------------------------------------------------------------------
    // Pipeline steps (each through its service, at its own time)
    // ------------------------------------------------------------------

    private function toScreening(string $key, int $daysAgo, string $time = '09:30'): void
    {
        $this->on($daysAgo, $time);
        $application = $this->fresh($key);
        app(ApplicationPipelineService::class)->advance($application, $application->current_vacancy_stage_id, $this->users['hro']);
    }

    private function screen(string $key, int $daysAgo, bool $passed, string $remarks, ?string $reason = null): void
    {
        $this->on($daysAgo, '14:00');
        app(ScreeningService::class)->record($this->fresh($key), [
            'result' => $passed ? 'passed' : 'failed',
            'screened_on' => $this->day($daysAgo),
            'remarks' => $remarks,
            'rejection_reason_id' => $passed ? null : $this->ids['reason'][$reason ?? 'failed_screening'],
        ], $this->users['hro']);
    }

    private function shortlist(string $key, int $daysAgo): void
    {
        $this->on($daysAgo, '16:00');
        $application = $this->fresh($key);
        app(ApplicationPipelineService::class)->shortlist($application, $application->current_vacancy_stage_id, $this->users['hro']);
    }

    private function advance(string $key, int $daysAgo, string $time = '10:00'): void
    {
        $this->on($daysAgo, $time);
        $application = $this->fresh($key);
        app(ApplicationPipelineService::class)->advance($application, $application->current_vacancy_stage_id, $this->users['hro']);
    }

    /** Applied → Screening → passed → Shortlisted → Interview. */
    private function toInterview(string $key, int $screeningDaysAgo, int $shortlistDaysAgo, int $interviewStageDaysAgo, string $remarks = 'Meets the requirements.'): void
    {
        $this->toScreening($key, $screeningDaysAgo);
        $this->screen($key, $screeningDaysAgo, true, $remarks);
        $this->shortlist($key, $shortlistDaysAgo);
        $this->advance($key, $interviewStageDaysAgo);
    }

    /**
     * @param  array<int, string>  $panel  employee keys (the first is primary)
     */
    private function interview(string $key, string $type, int $createdDaysAgo, int $slotDaysAgo, string $start, array $panel, string $mode = 'video'): ApplicationInterview
    {
        $this->on($createdDaysAgo, '08:30');

        return app(InterviewService::class)->schedule($this->fresh($key), [
            'interview_type_id' => $this->ids['interview'][$type],
            'mode' => $mode,
            'scheduled_date' => $this->day($slotDaysAgo),
            'start_time' => $start,
            'duration_minutes' => 60,
            'location' => $mode === 'in_person' ? 'Acme Tower 18F, Meeting Room 2' : null,
            'meeting_url' => $mode === 'video' ? 'https://meet.acmedigital.example/'.Str::lower(Str::random(10)) : null,
            'panelist_ids' => array_map(fn ($p) => $this->emp($p), $panel),
            'primary_panelist_id' => $this->emp($panel[0]),
        ], $this->users['hro']);
    }

    private function completeInterview(ApplicationInterview $interview, int $daysAgo, string $time, string $remarks = 'Interview held as scheduled.'): ApplicationInterview
    {
        $this->on($daysAgo, $time);

        return app(InterviewService::class)->complete($interview->fresh(), $remarks, $this->users['hro']);
    }

    /**
     * A panelist's scorecard: submitted, or left as a draft.
     */
    private function scorecard(ApplicationInterview $interview, string $panelist, int $daysAgo, int $rating, string $recommendation, string $comments, bool $submit = true): void
    {
        $this->on($daysAgo, '17:00');
        $data = [
            'recommendation' => $recommendation,
            'comments' => $comments,
            'scores' => collect($this->ids['criteria'])->values()->map(fn ($id, $i) => [
                'evaluation_criterion_id' => $id,
                'rating' => max(1, min(5, $rating + [0, 1, 0, -1, 0, 1][$i % 6])),
            ])->all(),
        ];

        $submit
            ? app(EvaluationService::class)->submit($interview->fresh(), $data, $this->users[$panelist])
            : app(EvaluationService::class)->saveDraft($interview->fresh(), $data, $this->users[$panelist]);
    }

    /** Complete interview, then every panelist submits a scorecard the next day. */
    private function heldInterview(string $key, string $type, int $createdDaysAgo, int $slotDaysAgo, string $start, array $panel, int $rating, string $recommendation = 'recommend', string $mode = 'video'): ApplicationInterview
    {
        $interview = $this->interview($key, $type, $createdDaysAgo, $slotDaysAgo, $start, $panel, $mode);
        $interview = $this->completeInterview($interview, $slotDaysAgo, Carbon::parse($start)->addHours(2)->format('H:i'));

        foreach ($panel as $i => $panelist) {
            $this->scorecard($interview, $panelist, $slotDaysAgo - 1, $rating - ($i % 2), $recommendation, 'Clear answers and relevant experience.');
        }

        return $interview;
    }

    private function assessment(string $key, string $type, int $createdDaysAgo, int $scheduledDaysAgo, ?string $assessor = null): ApplicationAssessment
    {
        $this->on($createdDaysAgo, '11:00');

        return app(AssessmentService::class)->create($this->fresh($key), [
            'assessment_type_id' => $this->ids['assessment'][$type],
            'scheduled_at' => $this->day($scheduledDaysAgo).' 10:00:00',
            'assessor_employee_id' => $assessor ? $this->emp($assessor) : null,
        ], $this->users['hro']);
    }

    private function completeAssessment(ApplicationAssessment $assessment, int $daysAgo, ?int $score, ?bool $passed = null, string $remarks = 'Completed within the time limit.'): void
    {
        $this->on($daysAgo, '15:00');
        app(AssessmentService::class)->complete($assessment->fresh(), [
            'score' => $score, 'maximum_score' => $score === null ? null : 100, 'passed' => $passed, 'remarks' => $remarks,
        ], $this->users['hro']);
    }

    /** Interview → Assessment (one completed scored assessment) → Evaluation. */
    private function assessedToEvaluation(string $key, string $type, int $assessmentStageDaysAgo, int $completedDaysAgo, int $score, string $assessor): void
    {
        $this->advance($key, $assessmentStageDaysAgo);
        $assessment = $this->assessment($key, $type, $assessmentStageDaysAgo, $completedDaysAgo, $assessor);
        $this->completeAssessment($assessment, $completedDaysAgo, $score);
        $this->advance($key, $completedDaysAgo - 1);
    }

    private function decide(string $key, string $decision, int $daysAgo, string $remarks): void
    {
        $this->on($daysAgo, '11:30');
        app(SelectionService::class)->decide($this->fresh($key), $decision, $remarks, $this->users['hrm']);
    }

    private function reject(string $key, string $reason, int $daysAgo, string $remarks): void
    {
        $this->on($daysAgo, '15:30');
        app(ApplicationPipelineService::class)->reject($this->fresh($key), $this->ids['reason'][$reason], $this->users['hro'], $remarks);
    }

    private function withdrawByHr(string $key, int $daysAgo, string $reason): void
    {
        $this->on($daysAgo, '13:00');
        app(ApplicationPipelineService::class)->withdraw($this->fresh($key), $reason, $this->users['hro']);
    }

    private function withdrawOnline(string $key, int $daysAgo, string $reason): void
    {
        $this->on($daysAgo, '21:00');
        app(CareersApplicationService::class)->withdraw($this->portalAccounts[$key], $this->fresh($key)->application_number, $reason);
    }

    // ------------------------------------------------------------------
    // Offers (draft → approval → issue → response)
    // ------------------------------------------------------------------

    private function draftOffer(string $key, int $daysAgo, int $expiresIn, int $startsIn, int $salary, string $status = 'Probationary'): JobOffer
    {
        $this->on($daysAgo, '09:30');
        $vacancy = $this->vacancies[$this->vacancyOf($key)];

        return app(OfferService::class)->createDraft($this->fresh($key), [
            'job_title_id' => $vacancy->job_title_id,
            'employment_status_id' => $this->statusId($status),
            'location_id' => $vacancy->location_id,
            'proposed_start_date' => $this->day($daysAgo - $startsIn),
            'expiry_date' => $this->day($daysAgo - $expiresIn),
            'base_salary' => $salary,
            'salary_frequency' => 'monthly',
            'currency' => 'PHP',
            'benefits' => 'HMO for employee and one dependent, 13th month pay, 15 vacation and 10 sick leave days.',
        ], $this->users['hro']);
    }

    private function submitOffer(JobOffer $offer, int $daysAgo): JobOffer
    {
        $this->on($daysAgo, '10:30');

        return app(OfferService::class)->submit($offer->fresh(), 'Terms aligned with the approved salary range.', $this->users['hro']);
    }

    private function approveOffer(JobOffer $offer, int $daysAgo): JobOffer
    {
        $this->on($daysAgo, '15:00');

        return app(OfferApprovalService::class)->approve($offer->fresh(), $this->users['hrm'], 'Approved.');
    }

    private function issueOffer(JobOffer $offer, int $daysAgo): JobOffer
    {
        $this->on($daysAgo, '10:00');

        return app(OfferService::class)->issue($offer->fresh(), 'Offer letter sent to the candidate.', $this->users['hro']);
    }

    private function respondOffer(JobOffer $offer, int $daysAgo, string $response, string $remarks): JobOffer
    {
        $this->on($daysAgo, '16:00');

        return app(OfferService::class)->respond($offer->fresh(), $response, $remarks, $this->users['hro']);
    }

    /** Draft, submit, approve and issue on consecutive days ending on $issuedDaysAgo. */
    private function issuedOffer(string $key, int $issuedDaysAgo, int $expiresIn, int $startsIn, int $salary, string $status = 'Probationary'): JobOffer
    {
        $offer = $this->draftOffer($key, $issuedDaysAgo + 2, $expiresIn + 2, $startsIn + 2, $salary, $status);
        $offer = $this->submitOffer($offer, $issuedDaysAgo + 2);
        $offer = $this->approveOffer($offer, $issuedDaysAgo + 1);

        return $this->issueOffer($offer, $issuedDaysAgo);
    }

    private function vacancyOf(string $key): string
    {
        return array_search($this->applications[$key]->vacancy_id, array_map(fn ($v) => $v->id, $this->vacancies), true);
    }

    // ------------------------------------------------------------------
    // Scenario: Sales Executive (closed, historical)
    // ------------------------------------------------------------------

    private function salesExecutiveClosed(): void
    {
        $requisition = $this->requisition('salm', [
            'department_id' => $this->deptId('SALES'), 'job_title_id' => $this->titleId('Sales Executive'),
            'location_id' => $this->locationId('North Branch'), 'employment_status_id' => $this->statusId('Probationary'),
            'positions' => 1, 'reason' => 'new_position', 'target_start_date' => $this->day(60),
            'justification' => 'Additional account coverage for the North region.',
        ], 120, [['coo', 'Approved for Q3 expansion.'], ['ceo', null]]);

        $this->openVacancy('sales', [
            'job_requisition_id' => $requisition->id, 'openings' => 1, 'title' => 'Sales Executive',
            'description' => 'Grow and manage client accounts in the North region.',
            'qualifications' => 'Two years of B2B sales experience.', 'responsibilities' => 'Prospecting, proposals, account management.',
            'salary_min' => 30000, 'salary_max' => 45000, 'salary_currency' => 'PHP',
            'closing_date' => $this->day(85), 'visibility' => 'internal', 'hiring_manager_id' => $this->emp('salm'),
        ], 115, false);

        $this->hrApplication('sales1', 'sales', ['Patrick', 'Domingo', 'Quezon City', 'University of Santo Tomas', 'BS Marketing', 'Northwind Trading', 'Account Executive', 4], 'job_board', 112);
        $this->hrApplication('sales2', 'sales', ['Clarissa', 'Uy', 'Makati City', 'De La Salle University', 'BS Business Management', 'Bluefin Retail', 'Sales Associate', 1], 'walk_in', 110);
        $this->hrApplication('sales3', 'sales', ['Ramil', 'Gatchalian', 'Caloocan City', 'Far Eastern University', 'BS Marketing', 'Sunrise Foods', 'Sales Representative', 3], 'referral', 108, details: 'Referred by Camille Rivera');
        $this->hrApplication('sales4', 'sales', ['Isabel', 'Manalo', 'Pasig City', 'University of the East', 'BS Entrepreneurship', 'Kanto Brands', 'Sales Coordinator', 2], 'social_media', 102);

        $this->toInterview('sales1', 106, 104, 103);
        $interview = $this->interview('sales1', 'initial_interview', 103, 96, '10:00', ['salm', 'hro'], 'in_person');
        $this->completeInterview($interview, 96, '12:00');
        $this->scorecard($interview, 'salm', 95, 4, 'recommend', 'Strong pipeline discipline.');
        $this->scorecard($interview, 'hro', 95, 3, 'neutral', 'Good fit; salary expectation at the top of the range.');

        $this->toScreening('sales2', 106);
        $this->screen('sales2', 105, false, 'Less than the required two years of B2B sales.', 'insufficient_experience');
        $this->withdrawByHr('sales3', 100, 'Candidate accepted another offer.');

        $this->reject('sales1', 'requirements_changed', 82, 'Sales headcount was frozen for the year.');
        $this->reject('sales4', 'requirements_changed', 82, 'Sales headcount was frozen for the year.');
        $this->on(81, '10:00');
        app(VacancyService::class)->transition($this->vacancies['sales'], Vacancy::STATUS_CLOSED, $this->users['hrm'], 'Headcount frozen for the rest of the year.');
    }

    // ------------------------------------------------------------------
    // Scenario: Accountant (filled, hire converted to employee)
    // ------------------------------------------------------------------

    private function accountantFilled(): void
    {
        $requisition = $this->requisition('finm', [
            'department_id' => $this->deptId('FIN'), 'job_title_id' => $this->titleId('Accountant'),
            'location_id' => $this->locationId('Main Corporate Office'), 'employment_status_id' => $this->statusId('Probationary'),
            'positions' => 1, 'reason' => 'new_position', 'target_start_date' => $this->day(40),
            'justification' => 'Month-end close workload grew with the new subsidiaries.',
        ], 100, [['ceo', 'Approved.']]);

        $this->openVacancy('accountant', [
            'job_requisition_id' => $requisition->id, 'openings' => 1, 'title' => 'Accountant',
            'description' => 'Prepare books of accounts, reconciliations and monthly reports.',
            'qualifications' => 'Licensed CPA; one to three years of experience.', 'responsibilities' => 'General ledger, reconciliations, month-end close.',
            'salary_min' => 35000, 'salary_max' => 45000, 'salary_currency' => 'PHP',
            'closing_date' => $this->day(70), 'visibility' => 'external', 'hiring_manager_id' => $this->emp('finm'),
        ], 95, false);

        $this->hrApplication('acct1', 'accountant', ['Maria', 'Reyes', 'Mandaluyong City', 'Polytechnic University of the Philippines', 'BS Accountancy', 'Prime Ledger Advisory', 'Junior Accountant', 3], 'job_board', 92, resume: true);
        $this->hrApplication('acct2', 'accountant', ['Jerome', 'Bautista', 'Pasig City', 'University of the Philippines Diliman', 'BS Accountancy', 'Coral Bay Holdings', 'Accounting Associate', 2], 'referral', 91, details: 'Referred by Mark Dizon');
        $this->hrApplication('acct3', 'accountant', ['Aileen', 'Soriano', 'Taguig City', 'Mapua University', 'BS Accounting Information Systems', 'Lakeside Mart', 'Bookkeeper', 1], 'job_board', 90);
        $this->hrApplication('acct4', 'accountant', ['Noel', 'Fajardo', 'Manila', 'Adamson University', 'BS Accountancy', 'Harbor Freight PH', 'Accounts Payable Clerk', 2], 'agency', 86);
        $this->hrApplication('acct5', 'accountant', ['Trisha', 'Lacson', 'Makati City', 'Ateneo de Manila University', 'BS Management Accounting', 'Granite Partners', 'Audit Associate', 2], 'walk_in', 85);

        $this->toInterview('acct1', 84, 82, 81, 'CPA; strong reconciliation experience.');
        $this->toInterview('acct2', 84, 82, 81);
        $this->toScreening('acct3', 84);
        $this->screen('acct3', 83, false, 'Not yet a licensed CPA.', 'not_qualified');
        $this->toScreening('acct5', 80);
        $this->screen('acct5', 80, true, 'Licensed CPA; awaiting the shortlist decision.');

        $this->heldInterview('acct1', 'hr_interview', 80, 76, '10:00', ['finm', 'hro'], 5, mode: 'in_person');
        $this->heldInterview('acct2', 'hr_interview', 80, 76, '14:00', ['finm', 'hro'], 4, mode: 'in_person');
        $this->assessedToEvaluation('acct1', 'written_assessment', 74, 72, 88, 'acct1');
        $this->assessedToEvaluation('acct2', 'written_assessment', 74, 72, 74, 'acct1');

        $this->decide('acct1', ApplicationSelection::SELECTED, 68, 'Strongest scores across the panel and the written assessment.');
        $this->decide('acct2', ApplicationSelection::NOT_SELECTED, 68, 'Good candidate; kept for future openings.');

        $offer = $this->issuedOffer('acct1', 65, 5, 25, 40000);
        $this->respondOffer($offer, 62, 'accepted', 'Accepted by phone and signed copy returned.');

        foreach (['acct2', 'acct4', 'acct5'] as $key) {
            $this->reject($key, 'position_filled', 41, 'The position has been filled.');
        }

        // Converted on the hire's first day (the offer's proposed start date).
        $this->on(40, '08:30');
        $resumeIds = DB::table('application_documents')->where('application_id', $this->applications['acct1']->id)->pluck('applicant_document_id')->all();
        app(ApplicationConversionService::class)->convert($this->fresh('acct1'), [
            'emp_sex' => 'female', 'supervisor_id' => $this->emp('finm'), 'document_ids' => $resumeIds,
        ], $this->users['hro']);

        $this->on(40, '10:00');
        app(VacancyService::class)->transition($this->vacancies['accountant'], Vacancy::STATUS_FILLED, $this->users['hrm'], 'Filled by Maria Reyes.');
    }

    // ------------------------------------------------------------------
    // Scenario: HR Specialist (open, two openings, offers)
    // ------------------------------------------------------------------

    private function hrSpecialistOffers(): void
    {
        $requisition = $this->requisition('hrm', [
            'department_id' => $this->deptId('HR'), 'job_title_id' => $this->titleId('HR Specialist'),
            'location_id' => $this->locationId('Main Corporate Office'), 'employment_status_id' => $this->statusId('Probationary'),
            'positions' => 2, 'reason' => 'new_position', 'target_start_date' => $this->day(-20),
            'justification' => 'Recruitment and onboarding volume doubled this year.',
        ], 75, [['ceo', 'Approved; please hire before Q4 peak.']]);

        $this->openVacancy('hrspec', [
            'job_requisition_id' => $requisition->id, 'openings' => 2, 'title' => 'HR Specialist',
            'description' => 'Run recruitment, onboarding and HR programs for a growing team.',
            'qualifications' => 'Two years in recruitment or HR operations.', 'responsibilities' => 'Sourcing, interviews, onboarding, HR programs.',
            'salary_min' => 38000, 'salary_max' => 52000, 'salary_currency' => 'PHP',
            'closing_date' => $this->day(-20), 'visibility' => 'both', 'hiring_manager_id' => $this->emp('hrm'),
        ], 70, true);

        $this->hrApplication('hr1', 'hrspec', ['Daniel', 'Aragon', 'Quezon City', 'Miriam College', 'BS Psychology', 'TalentBridge PH', 'Recruitment Associate', 3], 'job_board', 66);
        $this->portalApplication('hr2', 'hrspec', ['Sofia', 'Garcia', 'Pasig City', 'University of Santo Tomas', 'BS Psychology', 'Brightpath BPO', 'HR Generalist', 4], 60);
        $this->hrApplication('hr3', 'hrspec', ['Andrea', 'Lim', 'Makati City', 'De La Salle University', 'AB Psychology', 'Metro Staffing Co.', 'Talent Acquisition Specialist', 3], 'referral', 50, details: 'Referred by Jasmine Torres');
        $this->internalApplication('hr4', 'hrspec', 'csr1', 45);
        $this->portalApplication('hr5', 'hrspec', ['Kristine', 'Abad', 'Pasay City', 'Lyceum of the Philippines', 'BS Human Resource Management', 'Cityline Retail', 'HR Assistant', 0], 40);
        $this->portalApplication('hr6', 'hrspec', ['Joel', 'Santiago', 'Marikina City', 'Far Eastern University', 'BS Psychology', 'Evergreen Logistics', 'HR Coordinator', 2], 35);
        $this->hrApplication('hr7', 'hrspec', ['Bianca', 'Ferrer', 'Paranaque City', 'University of the East', 'BS Psychology', 'PeopleFirst Agency', 'Recruiter', 2], 'agency', 6);

        // Daniel Aragon: selected, offer declined, then marked not selected (opening released).
        $this->toInterview('hr1', 64, 62, 61);
        $this->heldInterview('hr1', 'panel_interview', 60, 54, '10:00', ['hrm', 'hro'], 4);
        $this->assessedToEvaluation('hr1', 'written_assessment', 52, 50, 82, 'hrs');
        $this->decide('hr1', ApplicationSelection::SELECTED, 44, 'Best panel feedback so far.');
        $offer = $this->issuedOffer('hr1', 40, 7, 30, 45000);
        $this->respondOffer($offer, 36, 'declined', 'Accepted a counter-offer from the current employer.');
        $this->decide('hr1', ApplicationSelection::NOT_SELECTED, 35, 'Offer declined; opening released.');

        // Sofia (careers portal): offer accepted, start date ahead (awaiting conversion).
        $this->toInterview('hr2', 58, 56, 55);
        $this->heldInterview('hr2', 'panel_interview', 54, 46, '14:00', ['hrm', 'hro'], 5);
        $this->assessedToEvaluation('hr2', 'written_assessment', 44, 42, 91, 'hrs');
        $this->decide('hr2', ApplicationSelection::SELECTED, 32, 'Excellent references and assessment.');
        $offer = $this->issuedOffer('hr2', 29, 7, 40, 48000);
        $this->respondOffer($offer, 25, 'accepted', 'Signed offer received.');

        // Andrea Lim: selected, offer issued and awaiting her response.
        $this->toInterview('hr3', 48, 46, 45);
        $this->heldInterview('hr3', 'panel_interview', 44, 30, '10:00', ['hrm', 'hro'], 4);
        $this->assessedToEvaluation('hr3', 'written_assessment', 28, 26, 85, 'hrs');
        $this->decide('hr3', ApplicationSelection::SELECTED, 16, 'Strong sourcing background.');
        $this->issuedOffer('hr3', 6, 8, 30, 46000);

        // Andrea Morales (internal): interviewed, scorecards in, still at Interview.
        $this->toInterview('hr4', 43, 41, 40, 'Internal candidate with excellent performance reviews.');
        $this->heldInterview('hr4', 'hr_interview', 30, 11, '15:00', ['hrm', 'hro'], 4, mode: 'in_person');

        $this->toScreening('hr5', 38);
        $this->screen('hr5', 37, false, 'No HR experience yet.', 'insufficient_experience');
        $this->withdrawOnline('hr6', 31, 'I have decided to stay with my current employer.');
        $this->toScreening('hr7', 4);
    }

    // ------------------------------------------------------------------
    // Scenario: IT Support Specialist (on hold, then cancelled)
    // ------------------------------------------------------------------

    private function itSupportCancelled(): void
    {
        $this->openVacancy('itsupport', [
            'job_title_id' => $this->titleId('IT Support Specialist'), 'department_id' => $this->deptId('IT'),
            'location_id' => $this->locationId('Operations Center'), 'employment_status_id' => $this->statusId('Contractual'),
            'openings' => 1, 'title' => 'IT Support Specialist (Contract)',
            'description' => 'Six-month contract for the Operations Center help desk.',
            'qualifications' => 'One year of end-user support.', 'responsibilities' => 'Help desk tickets, device setup.',
            'salary_min' => 25000, 'salary_max' => 30000, 'salary_currency' => 'PHP',
            'closing_date' => $this->day(30), 'visibility' => 'internal', 'hiring_manager_id' => $this->emp('itm'),
        ], 58, false);

        $this->hrApplication('its1', 'itsupport', ['Mark', 'Villanueva', 'Taguig City', 'STI College', 'BS Information Technology', 'QuickFix Computers', 'Technical Support', 2], 'walk_in', 56);
        $this->hrApplication('its2', 'itsupport', ['Rhea', 'Castro', 'Pateros', 'AMA Computer College', 'BS Computer Science', 'Netcore Services', 'IT Help Desk', 1], 'job_board', 55);
        $this->toScreening('its1', 54);
        $this->screen('its1', 54, true, 'Hands-on hardware experience.');

        $this->reject('its1', 'requirements_changed', 52, 'The help desk will be outsourced.');
        $this->reject('its2', 'requirements_changed', 52, 'The help desk will be outsourced.');
        $this->on(51, '09:00');
        app(VacancyService::class)->transition($this->vacancies['itsupport'], Vacancy::STATUS_ON_HOLD, $this->users['hrm'], 'Outsourcing under review.');
        $this->on(48, '09:00');
        app(VacancyService::class)->transition($this->vacancies['itsupport'], Vacancy::STATUS_CANCELLED, $this->users['hrm'], 'Help desk outsourced.');
    }

    // ------------------------------------------------------------------
    // Scenario: Customer Support Representative (four openings)
    // ------------------------------------------------------------------

    private function customerSupportOpenings(): void
    {
        $requisition = $this->requisition('csm', [
            'department_id' => $this->deptId('CS'), 'job_title_id' => $this->titleId('Customer Support Representative'),
            'location_id' => $this->locationId('Operations Center'), 'employment_status_id' => $this->statusId('Probationary'),
            'positions' => 4, 'reason' => 'new_position', 'target_start_date' => $this->day(-30),
            'justification' => 'New enterprise clients need extended support hours.',
        ], 52, [['coo', 'Approved.'], ['ceo', 'Approved; stagger the start dates.']]);

        $this->openVacancy('csr', [
            'job_requisition_id' => $requisition->id, 'openings' => 4, 'title' => 'Customer Support Representative',
            'description' => 'Help Acme customers by phone, chat and email.',
            'qualifications' => 'Excellent written and spoken English; shifting schedule.', 'responsibilities' => 'Tickets, calls, escalations.',
            'salary_min' => 22000, 'salary_max' => 28000, 'salary_currency' => 'PHP',
            'closing_date' => $this->day(-25), 'visibility' => 'both', 'hiring_manager_id' => $this->emp('csm'),
        ], 48, true);

        $this->hrApplication('cs1', 'csr', ['Michael', 'Tan', 'Taguig City', 'Jose Rizal University', 'BS Tourism Management', 'Callpoint Solutions', 'Customer Service Associate', 3], 'agency', 46);
        $this->portalApplication('cs2', 'csr', ['Angelica', 'Ramos', 'Pasig City', 'Rizal Technological University', 'AB Communication', 'HelpLine Asia', 'Chat Support', 2], 45);
        $this->hrApplication('cs3', 'csr', ['Carlo', 'Mendoza', 'Quezon City', 'Bulacan State University', 'BS Business Administration', 'Telenet BPO', 'Technical Support Representative', 2], 'job_board', 45);
        $this->hrApplication('cs4', 'csr', ['Liza', 'Santos', 'Muntinlupa City', 'San Beda University', 'AB English', 'Global Reach BPO', 'Email Support', 1], 'walk_in', 44);
        $this->hrApplication('cs5', 'csr', ['James', 'Navarro', 'Manila', 'University of Makati', 'BS Hospitality Management', 'Hotel Luna', 'Front Desk Agent', 2], 'referral', 42, details: 'Referred by Luis Mercado');
        $this->portalApplication('cs6', 'csr', ['Patricia', 'Ocampo', 'Taguig City', 'Technological University of the Philippines', 'BS Information Technology', 'ServiceDesk PH', 'Service Desk Analyst', 1], 40);
        $this->hrApplication('cs7', 'csr', ['Alex', 'Santos', 'Pasig City', 'Pamantasan ng Lungsod ng Maynila', 'AB Communication', 'Bright Call Center', 'Customer Care Associate', 1], 'job_board', 30);
        $this->hrApplication('cs8', 'csr', ['Bea', 'Morales', 'Caloocan City', 'Our Lady of Fatima University', 'BS Psychology', '', '', 0], 'social_media', 28);
        $this->hrApplication('cs9', 'csr', ['Ryan', 'Del Rosario', 'Valenzuela City', 'National University', 'BS Computer Engineering', 'ConnectOne', 'Inbound Sales Agent', 2], 'job_board', 26);
        $this->portalApplication('cs10', 'csr', ['Nicole', 'Aquino', 'Mandaluyong City', 'University of the Philippines Manila', 'AB Organizational Communication', 'Atlas Contact Center', 'Customer Service Associate', 2], 1);

        foreach (['cs1' => [40, 38, 37], 'cs2' => [40, 38, 37], 'cs3' => [39, 38, 37], 'cs4' => [39, 38, 37], 'cs5' => [36, 35, 35], 'cs6' => [36, 35, 35]] as $key => [$screening, $shortlist, $interviewStage]) {
            $this->toInterview($key, $screening, $shortlist, $interviewStage, 'Good communication skills on the phone screen.');
        }

        $slots = ['cs3' => [33, '09:00', 5], 'cs4' => [33, '11:00', 4], 'cs2' => [32, '09:00', 4], 'cs1' => [32, '11:00', 5], 'cs5' => [31, '09:00', 3]];
        foreach ($slots as $key => [$slot, $time, $rating]) {
            $this->heldInterview($key, 'initial_interview', 34, $slot, $time, ['csm', 'hro'], $rating, $rating >= 4 ? 'recommend' : 'neutral');
        }
        foreach (['cs3' => 87, 'cs4' => 80, 'cs2' => 84, 'cs1' => 90, 'cs5' => 70] as $key => $score) {
            $this->assessedToEvaluation($key, 'skills_assessment', 29, 27, $score, 'csm');
        }

        // Carlo Mendoza: offer issued but never answered, so it expired.
        $this->decide('cs3', ApplicationSelection::SELECTED, 25, 'Top assessment score in the first batch.');
        $offer = $this->issuedOffer('cs3', 22, 6, 20, 25000);
        $this->on(15, '00:10'); // the daily recruitment:expire-offers run, the day after the expiry date
        app(OfferService::class)->expireIfDue($offer->fresh());
        $this->decide('cs3', ApplicationSelection::NOT_SELECTED, 15, 'Offer expired without a response; opening released.');

        // Liza Santos: offer withdrawn before approval, application rejected (opening released automatically).
        $this->decide('cs4', ApplicationSelection::SELECTED, 22, 'Good fit for the email support queue.');
        $offer = $this->draftOffer('cs4', 21, 10, 25, 24000);
        $this->on(19, '11:00');
        app(OfferService::class)->withdraw($offer->fresh(), 'Candidate is not available for the night shift.', $this->users['hro']);
        $this->reject('cs4', 'other', 19, 'Not available for the required shift schedule.');

        // Angelica Ramos (careers portal): offer approved, not issued yet.
        $this->decide('cs2', ApplicationSelection::SELECTED, 20, 'Strong chat support background.');
        $offer = $this->draftOffer('cs2', 6, 10, 25, 25000);
        $offer = $this->submitOffer($offer, 5);
        $this->approveOffer($offer, 4);

        // Michael Tan: first offer rejected in approval, revised offer pending approval.
        $this->decide('cs1', ApplicationSelection::SELECTED, 18, 'Best overall interview and assessment.');
        $offer = $this->draftOffer('cs1', 17, 10, 25, 31000);
        $offer = $this->submitOffer($offer, 17);
        $this->on(16, '14:00');
        app(OfferApprovalService::class)->reject($offer->fresh(), $this->users['hrm'], 'Salary is above the approved range (PHP 28,000).');
        $offer = $this->draftOffer('cs1', 3, 10, 25, 28000);
        $this->submitOffer($offer, 2);

        // James Navarro: evaluated, not selected.
        $this->decide('cs5', ApplicationSelection::NOT_SELECTED, 12, 'Assessment below the team\'s threshold.');

        // Patricia Ocampo (careers portal): interviewed, assessment scheduled for next week.
        $this->heldInterview('cs6', 'initial_interview', 20, 12, '10:00', ['csm', 'hro'], 4);
        $this->advance('cs6', 9);
        $this->assessment('cs6', 'skills_assessment', 9, -3, 'csm');

        // Alex Santos: interview rescheduled to the day after tomorrow.
        $this->toInterview('cs7', 24, 22, 21);
        $interview = $this->interview('cs7', 'initial_interview', 5, -1, '14:00', ['csm', 'hro']);
        $this->on(2, '16:00');
        app(InterviewService::class)->reschedule($interview->fresh(), [
            'scheduled_date' => $this->day(-2), 'start_time' => '14:00', 'duration_minutes' => 60, 'reason' => 'Candidate requested a later date.',
        ], $this->users['hro']);

        $this->toScreening('cs8', 25);
        $this->screen('cs8', 24, false, 'No customer service experience; English assessment below the requirement.', 'not_qualified');
        $this->withdrawByHr('cs9', 15, 'Candidate accepted a job closer to home.');
    }

    // ------------------------------------------------------------------
    // Scenario: Senior Software Developer (main active vacancy)
    // ------------------------------------------------------------------

    private function seniorDeveloperActive(): void
    {
        $requisition = $this->requisition('itm', [
            'department_id' => $this->deptId('IT'), 'job_title_id' => $this->titleId('Senior Software Developer'),
            'location_id' => $this->locationId('Main Corporate Office'), 'employment_status_id' => $this->statusId('Regular'),
            'positions' => 3, 'reason' => 'new_position', 'target_start_date' => $this->day(-45),
            'justification' => 'Client portal and HRMS product roadmap need two senior engineers now and one next quarter.',
        ], 45, [['ceo', 'Approved two now; the third after Q1 planning.']]);

        $this->openVacancy('seniordev', [
            'job_requisition_id' => $requisition->id, 'openings' => 2, 'title' => 'Senior Software Engineer',
            'description' => 'Lead features end to end on our Laravel and Vue products.',
            'qualifications' => 'Five years of web development; Laravel and Vue experience.', 'responsibilities' => 'Design, build, review and mentor.',
            'salary_min' => 90000, 'salary_max' => 120000, 'salary_currency' => 'PHP',
            'closing_date' => $this->day(-30), 'visibility' => 'both', 'hiring_manager_id' => $this->emp('itm'),
        ], 40, true);

        $this->internalApplication('dev1', 'seniordev', 'dev1', 38);
        $this->hrApplication('dev2', 'seniordev', ['Gabriel', 'Lopez', 'Makati City', 'Ateneo de Manila University', 'BS Computer Science', 'Cloudnine Labs', 'Software Engineer', 6], 'referral', 37, details: 'Referred by Angela Bautista');
        $this->hrApplication('dev3', 'seniordev', ['Victor', 'Ramos', 'Quezon City', 'University of the Philippines Diliman', 'BS Computer Science', 'Pixelwave Studio', 'Full Stack Developer', 5], 'job_board', 36);
        $this->portalApplication('dev4', 'seniordev', ['Erika', 'Gonzaga', 'Pasig City', 'Mapua University', 'BS Computer Engineering', 'Stackhouse Inc.', 'Senior Web Developer', 7], 34);
        $this->portalApplication('dev5', 'seniordev', ['Paolo', 'Reyes', 'Taguig City', 'De La Salle University', 'BS Software Technology', 'Bytecraft PH', 'Backend Engineer', 5], 30);
        $this->hrApplication('dev6', 'seniordev', ['Jasper', 'Lim', 'Mandaluyong City', 'University of Santo Tomas', 'BS Information Technology', 'Orbit Systems', 'Software Developer', 5], 'social_media', 28);
        $this->hrApplication('dev7', 'seniordev', ['Hazel', 'Tolentino', 'Paranaque City', 'Technological Institute of the Philippines', 'BS Computer Science', 'Nimbus Tech', 'Frontend Engineer', 6], 'agency', 20);
        $this->hrApplication('dev8', 'seniordev', ['Kenneth', 'Ong', 'Manila', 'University of Santo Tomas', 'BS Computer Science', 'Ledgerline', 'PHP Developer', 4], 'job_board', 12);
        $this->hrApplication('dev9', 'seniordev', ['Rowena', 'Pascual', 'Las Pinas City', 'Adamson University', 'BS Information Technology', 'Westgate Media', 'Web Designer', 2], 'walk_in', 15);
        $this->portalApplication('dev10', 'seniordev', ['Miguel', 'Fernandez', 'San Juan City', 'University of the Philippines Diliman', 'BS Computer Science', 'Arcadia Games', 'Lead Developer', 8], 2);
        $this->portalApplication('dev11', 'seniordev', ['Danica', 'Uy', 'Quezon City', 'Ateneo de Manila University', 'BS Management Information Systems', 'Helix Fintech', 'Software Engineer II', 5], 25);

        // Kevin Lim (internal): two interview rounds, assessment, Evaluation complete; awaiting a decision.
        $this->toInterview('dev1', 36, 34, 33, 'Internal candidate; strong delivery record.');
        $this->heldInterview('dev1', 'technical_interview', 32, 24, '10:00', ['itm', 'sdev'], 5);
        $this->heldInterview('dev1', 'final_interview', 22, 17, '10:00', ['itm', 'hrm'], 4, mode: 'in_person');
        $this->assessedToEvaluation('dev1', 'technical_assessment', 15, 12, 89, 'sdev');

        // Gabriel Lopez: in Evaluation, one scorecard still a draft (selection not possible yet).
        $this->toInterview('dev2', 35, 34, 33);
        $interview = $this->interview('dev2', 'technical_interview', 32, 23, '14:00', ['itm', 'sdev']);
        $interview = $this->completeInterview($interview, 23, '16:00');
        $this->scorecard($interview, 'sdev', 22, 4, 'recommend', 'Solid system design answers.');
        $this->scorecard($interview, 'itm', 21, 4, 'recommend', 'Draft: confirm availability for on-site days.', submit: false);
        $this->assessedToEvaluation('dev2', 'technical_assessment', 14, 10, 84, 'sdev');

        // Victor Ramos: failed the practical exercise, rejected.
        $this->toInterview('dev3', 34, 33, 32);
        $this->heldInterview('dev3', 'technical_interview', 31, 22, '10:00', ['itm', 'sdev'], 3, 'neutral');
        $this->advance('dev3', 20);
        $assessment = $this->assessment('dev3', 'practical_assessment', 20, 16, 'sdev');
        $this->completeAssessment($assessment, 16, null, false, 'Did not complete the take-home exercise requirements.');
        $this->reject('dev3', 'not_qualified', 14, 'Practical exercise below the senior bar.');

        // Erika Gonzaga (careers portal): technical assessment in progress.
        $this->toInterview('dev4', 32, 30, 29);
        $this->heldInterview('dev4', 'technical_interview', 28, 18, '14:00', ['itm', 'sdev'], 4);
        $this->advance('dev4', 6);
        $assessment = $this->assessment('dev4', 'technical_assessment', 6, 2, 'sdev');
        $this->on(2, '10:05');
        app(AssessmentService::class)->start($assessment->fresh(), $this->users['hro']);

        // Paolo Reyes (careers portal): technical interview rescheduled to tomorrow.
        $this->toInterview('dev5', 27, 25, 24);
        $interview = $this->interview('dev5', 'technical_interview', 6, 1, '10:00', ['itm', 'sdev']);
        $this->on(3, '09:00');
        app(InterviewService::class)->reschedule($interview->fresh(), [
            'scheduled_date' => $this->day(-1), 'start_time' => '10:00', 'duration_minutes' => 60, 'reason' => 'Panelist conflict with a client release.',
        ], $this->users['hro']);

        // Jasper Lim: interview done, scorecards in; still at Interview.
        $this->toInterview('dev6', 25, 23, 22);
        $this->heldInterview('dev6', 'technical_interview', 12, 8, '14:00', ['itm', 'sdev'], 4);

        // Hazel Tolentino: shortlisted.
        $this->toScreening('dev7', 16);
        $this->screen('dev7', 15, true, 'Strong Vue portfolio.');
        $this->shortlist('dev7', 12);

        // Kenneth Ong: in Screening; Rowena Pascual: failed screening.
        $this->toScreening('dev8', 10);
        $this->toScreening('dev9', 12);
        $this->screen('dev9', 10, false, 'Design background; not enough backend experience.', 'insufficient_experience');

        // Danica Uy (careers portal): passed screening, then withdrew online.
        $this->toScreening('dev11', 22);
        $this->screen('dev11', 21, true, 'Good fintech experience.');
        $this->withdrawOnline('dev11', 18, 'I accepted an offer elsewhere. Thank you for the opportunity.');
    }

    // ------------------------------------------------------------------
    // Requisitions still in approval, and a rejected one
    // ------------------------------------------------------------------

    private function otherRequisitions(): void
    {
        $this->requisition('opsm', [
            'department_id' => $this->deptId('OPS'), 'job_title_id' => $this->titleId('Operations Officer'),
            'location_id' => $this->locationId('Operations Center'), 'employment_status_id' => $this->statusId('Probationary'),
            'positions' => 2, 'reason' => 'new_position', 'target_start_date' => $this->day(-60),
            'justification' => 'Second shift for the logistics client starting next quarter.',
        ], 6, []); // awaiting the COO's approval

        $rejected = $this->requisition('admm', [
            'department_id' => $this->deptId('ADMIN'), 'job_title_id' => $this->titleId('Administrative Officer'),
            'location_id' => $this->locationId('Main Corporate Office'), 'employment_status_id' => $this->statusId('Contractual'),
            'positions' => 1, 'reason' => 'replacement', 'replaced_employee_id' => $this->emp('sep2'), 'target_start_date' => $this->day(-30),
            'justification' => 'Replacement for a retired administrative officer.',
        ], 20, []);
        $this->on(18, '11:00');
        app(RequisitionApprovalService::class)->reject($rejected, $this->users['coo'], 'Duties were absorbed by the shared services team.');
    }

    // ------------------------------------------------------------------
    // Fictional documents (generated PDFs on the private disk)
    // ------------------------------------------------------------------

    private function resume(Applicant $applicant, $actor)
    {
        $profile = ['first_name' => $applicant->first_name, 'last_name' => $applicant->last_name, 'email' => $applicant->email,
            'education' => $applicant->education()->get()->toArray(), 'work_experience' => $applicant->workExperience()->get()->toArray()];

        return app(ApplicantDocumentService::class)->upload($applicant, $this->demoPdf("{$applicant->first_name} {$applicant->last_name} - Resume", $this->resumeLines($profile)), [
            'applicant_document_type_id' => $this->ids['document']['resume'],
        ], $actor);
    }

    private function resumeLines(array $profile): array
    {
        $lines = ['FICTIONAL DEMO DOCUMENT - Acme Digital Solutions demo data', '', "Email: {$profile['email']}", ''];
        foreach ($profile['education'] ?? [] as $row) {
            $lines[] = "Education: {$row['degree']}, {$row['institute']}";
        }
        foreach ($profile['work_experience'] ?? [] as $row) {
            $lines[] = "Experience: {$row['job_title']} at {$row['company']}";
        }

        return $lines;
    }

    /**
     * A small, valid one-page PDF (Helvetica text) wrapped as an upload.
     */
    private function demoPdf(string $title, array $lines): UploadedFile
    {
        $escape = fn (string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($s));
        $text = 'BT /F1 18 Tf 72 730 Td ('.$escape($title).') Tj ET';
        foreach (array_values($lines) as $i => $line) {
            $text .= sprintf("\nBT /F1 11 Tf 72 %d Td (%s) Tj ET", 700 - $i * 16, $escape($line));
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($text)." >>\nstream\n{$text}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        $base = tempnam(sys_get_temp_dir(), 'acme-demo-');
        $path = $base.'.pdf';
        file_put_contents($path, $pdf);
        array_push($this->tempFiles, $base, $path);

        return new UploadedFile($path, Str::slug($title).'.pdf', 'application/pdf', null, true);
    }
}
