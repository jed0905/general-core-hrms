<?php

namespace Tests\Feature\Recruitment;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationInterview;
use App\Models\ApplicationScreening;
use App\Models\ApplicationSelection;
use App\Models\AssessmentType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\EvaluationCriterion;
use App\Models\InterviewType;
use App\Models\JobOffer;
use App\Models\JobRequisition;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\RejectionReason;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyStage;
use App\Services\Recruitment\ApplicantService;
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
use Illuminate\Support\Carbon;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * Fixture: an IT department where Ramon (supervisor) requests hires. His
 * supervisor is Maya (step 1 approver) and hers is Dina (step 2 approver).
 * HR staff and an HR director have organization-wide access.
 */
abstract class RecruitmentTestCase extends LeaveTestCase
{
    protected Department $it;

    protected JobTitle $developer;

    protected Location $mainOffice;

    protected EmploymentStatus $regular;

    protected User $dina;      // director, step-2 approver (hr_director)

    protected User $maya;      // manager, step-1 approver (supervisor)

    protected User $ramon;     // requester (supervisor)

    protected User $hrStaff;   // organization-wide, no approving

    protected User $outsider;  // a supervisor with no link to these requisitions

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-04 09:00:00');

        $this->it = Department::create(['name' => 'Information Technology', 'shortcut' => 'IT']);
        $this->developer = JobTitle::create(['job_title' => 'Software Developer']);
        $this->mainOffice = Location::create(['address' => 'Main Office', 'city' => 'Pasig City']);
        $this->regular = EmploymentStatus::create(['name' => 'Regular']);

        $this->dina = $this->userWithRole('hr_director', ['emp_first_name' => 'Dina', 'emp_last_name' => 'Director', 'department_id' => $this->it->id]);
        $this->maya = $this->userWithRole('supervisor', ['emp_first_name' => 'Maya', 'emp_last_name' => 'Manager', 'supervisor_id' => $this->dina->employee_id, 'department_id' => $this->it->id]);
        $this->ramon = $this->userWithRole('supervisor', ['emp_first_name' => 'Ramon', 'emp_last_name' => 'Requester', 'supervisor_id' => $this->maya->employee_id, 'department_id' => $this->it->id]);
        $this->hrStaff = $this->userWithRole('hr_staff', ['emp_first_name' => 'Hana', 'emp_last_name' => 'Staff']);
        $this->outsider = $this->userWithRole('supervisor', ['emp_first_name' => 'Otto', 'emp_last_name' => 'Outsider']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function requisitionPayload(array $overrides = []): array
    {
        return array_merge([
            'department_id' => $this->it->id,
            'job_title_id' => $this->developer->id,
            'location_id' => $this->mainOffice->id,
            'employment_status_id' => $this->regular->id,
            'positions' => 2,
            'reason' => 'new_position',
            'justification' => 'New product team.',
            'target_start_date' => '2026-11-02',
        ], $overrides);
    }

    protected function draftRequisition(array $overrides = [], ?User $as = null): JobRequisition
    {
        $as ??= $this->ramon;

        return app(RequisitionService::class)->createRequisition(
            $this->requisitionPayload($overrides) + ['requested_by_employee_id' => $as->employee_id],
            $as
        );
    }

    protected function submittedRequisition(array $overrides = []): JobRequisition
    {
        return app(RequisitionService::class)->submit($this->draftRequisition($overrides), $this->ramon);
    }

    protected function approvedRequisition(array $overrides = []): JobRequisition
    {
        $requisition = $this->submittedRequisition($overrides);
        app(RequisitionApprovalService::class)->approve($requisition, $this->maya);

        return app(RequisitionApprovalService::class)->approve($requisition, $this->dina);
    }

    protected function vacancyPayload(array $overrides = []): array
    {
        return array_merge([
            'job_title_id' => $this->developer->id,
            'department_id' => $this->it->id,
            'location_id' => $this->mainOffice->id,
            'employment_status_id' => $this->regular->id,
            'openings' => 2,
            'description' => 'Build and maintain our HR platform.',
            'qualifications' => '3+ years of PHP.',
            'visibility' => 'external',
        ], $overrides);
    }

    protected function draftVacancy(array $overrides = []): Vacancy
    {
        return app(VacancyService::class)->createVacancy($this->vacancyPayload($overrides), $this->hrStaff);
    }

    protected function openVacancy(array $overrides = []): Vacancy
    {
        return app(VacancyService::class)->transition($this->draftVacancy($overrides), Vacancy::STATUS_OPEN, $this->hrStaff);
    }

    // ----------------------------------------------------------- phase 2 helpers

    protected function applicantPayload(array $overrides = []): array
    {
        static $n = 0;
        $n++;

        return array_merge([
            'first_name' => 'Juan',
            'last_name' => "Dela Cruz {$n}",
            'email' => "juan{$n}@example.test",
            'phone' => '0917 555 '.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'privacy_consent' => true,
        ], $overrides);
    }

    protected function applicant(array $overrides = []): Applicant
    {
        return app(ApplicantService::class)->createApplicant($this->applicantPayload($overrides), $this->hrStaff);
    }

    protected function applyTo(Vacancy $vacancy, ?Applicant $applicant = null, array $data = []): Application
    {
        return app(ApplicationService::class)->createApplication($applicant ?? $this->applicant(), $vacancy, $data, $this->hrStaff);
    }

    protected function stage(Vacancy $vacancy, string $type): VacancyStage
    {
        return $vacancy->stages()->where('stage_type', $type)->firstOrFail();
    }

    protected function reason(string $code = 'not_qualified'): RejectionReason
    {
        return RejectionReason::where('code', $code)->firstOrFail();
    }

    /** An application moved to Screening (and optionally screened). */
    protected function inScreening(Vacancy $vacancy, ?string $result = null): Application
    {
        $application = $this->applyTo($vacancy);
        app(ApplicationPipelineService::class)->advance($application, $application->current_vacancy_stage_id, $this->hrStaff);

        if ($result) {
            app(ScreeningService::class)->record($application, [
                'result' => $result,
                'screened_on' => '2026-10-04',
                'remarks' => 'Phone screen.',
                'rejection_reason_id' => $result === ApplicationScreening::RESULT_FAILED ? $this->reason('failed_screening')->id : null,
            ], $this->hrStaff);
        }

        return $application->fresh();
    }

    protected function employee(User $user): Employee
    {
        return Employee::findOrFail($user->employee_id);
    }

    // ----------------------------------------------------------- phase 3 helpers

    /** A passed-screening application on the shortlist. */
    protected function shortlisted(Vacancy $vacancy): Application
    {
        $application = $this->inScreening($vacancy, ApplicationScreening::RESULT_PASSED);

        return app(ApplicationPipelineService::class)->shortlist($application, $application->current_vacancy_stage_id, $this->hrStaff)->fresh();
    }

    /** Move one stage forward through the pipeline service. */
    protected function moveOn(Application $application, ?User $as = null): Application
    {
        return app(ApplicationPipelineService::class)->advance($application, $application->fresh()->current_vacancy_stage_id, $as ?? $this->hrStaff)->fresh();
    }

    protected function inInterview(Vacancy $vacancy): Application
    {
        return $this->moveOn($this->shortlisted($vacancy));
    }

    protected function interviewType(string $code = 'hr_interview'): InterviewType
    {
        return InterviewType::where('code', $code)->firstOrFail();
    }

    protected function interviewPayload(array $panelists, array $overrides = []): array
    {
        return array_merge([
            'interview_type_id' => $this->interviewType()->id,
            'mode' => 'video',
            'meeting_url' => 'https://meet.example.test/abc',
            'scheduled_date' => '2026-10-05',
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'panelist_ids' => array_map(fn ($p) => $p instanceof User ? $p->employee_id : $p, $panelists),
        ], $overrides);
    }

    protected function scheduleInterview(Application $application, array $panelists, array $overrides = []): ApplicationInterview
    {
        return app(InterviewService::class)->schedule($application, $this->interviewPayload($panelists, $overrides), $this->hrStaff);
    }

    /** Schedule then complete an interview (time travels past its start). */
    protected function completedInterview(Application $application, array $panelists, array $overrides = []): ApplicationInterview
    {
        $interview = $this->scheduleInterview($application, $panelists, $overrides);
        Carbon::setTestNow($interview->starts_at->copy()->addHours(2));

        return app(InterviewService::class)->complete($interview, 'Went well.', $this->hrStaff)->fresh();
    }

    protected function assessmentType(string $code = 'technical_assessment'): AssessmentType
    {
        return AssessmentType::where('code', $code)->firstOrFail();
    }

    protected function inAssessment(Vacancy $vacancy, array $panelists = []): Application
    {
        $application = $this->inInterview($vacancy);
        $this->completedInterview($application, $panelists ?: [$this->maya]);

        return $this->moveOn($application);
    }

    protected function inEvaluation(Vacancy $vacancy): Application
    {
        $application = $this->inAssessment($vacancy);
        $assessment = app(AssessmentService::class)->create($application, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);
        app(AssessmentService::class)->complete($assessment, ['score' => 80, 'maximum_score' => 100], $this->hrStaff);

        return $this->moveOn($application);
    }

    // ----------------------------------------------------------- phase 4 helpers

    /**
     * Offer approvals start from the submitter's supervisor: HR staff (Hana)
     * reports to an HR manager, who reports to Dina (hr_director).
     */
    protected function hrApprovalChain(): User
    {
        $manager = $this->userWithRole('hr_manager', ['emp_first_name' => 'Mona', 'emp_last_name' => 'Manager', 'supervisor_id' => $this->dina->employee_id]);
        Employee::whereKey($this->hrStaff->employee_id)->update(['supervisor_id' => $manager->employee_id]);

        return $manager;
    }

    /** An application in Evaluation whose panel has submitted every scorecard. */
    protected function evaluated(Vacancy $vacancy, ?User $panelist = null): Application
    {
        $panelist ??= $this->maya;
        $application = $this->inInterview($vacancy);
        $interview = $this->completedInterview($application, [$panelist]);
        app(EvaluationService::class)->submit($interview, ['recommendation' => 'recommend', 'scores' => $this->scores(4)], $panelist);
        $application = $this->moveOn($application);
        $assessment = app(AssessmentService::class)->create($application, ['assessment_type_id' => $this->assessmentType()->id], $this->hrStaff);
        app(AssessmentService::class)->complete($assessment, ['score' => 85, 'maximum_score' => 100], $this->hrStaff);

        return $this->moveOn($application);
    }

    protected function select(Application $application, ?User $as = null): ApplicationSelection
    {
        return app(SelectionService::class)->decide($application, ApplicationSelection::SELECTED, 'Strongest panel feedback.', $as ?? $this->dina);
    }

    protected function offerTerms(array $overrides = []): array
    {
        return array_merge([
            'job_title_id' => $this->developer->id,
            'employment_status_id' => $this->regular->id,
            'location_id' => $this->mainOffice->id,
            'proposed_start_date' => '2026-11-02',
            'expiry_date' => '2026-10-20',
            'base_salary' => '55000.00',
            'salary_frequency' => 'monthly',
            'currency' => 'PHP',
            'benefits' => 'HMO, 13th month',
        ], $overrides);
    }

    protected function draftOffer(Application $application, array $overrides = []): JobOffer
    {
        return app(OfferService::class)->createDraft($application, $this->offerTerms($overrides), $this->hrStaff);
    }

    /** Draft, submit and approve through the HR chain (needs hrApprovalChain()). */
    protected function approvedOffer(Application $application, User $manager): JobOffer
    {
        $offer = app(OfferService::class)->submit($this->draftOffer($application), null, $this->hrStaff);
        app(OfferApprovalService::class)->approve($offer, $manager);

        return app(OfferApprovalService::class)->approve($offer, $this->dina);
    }

    /** A rating for every active criterion. */
    protected function scores(int $rating = 4): array
    {
        return EvaluationCriterion::active()->pluck('id')->map(fn ($id) => ['evaluation_criterion_id' => $id, 'rating' => $rating])->all();
    }
}
