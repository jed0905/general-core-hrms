<?php

/**
 * Worker process for RecruitmentConcurrencyTest. Scratch MySQL database only:
 * DB_DATABASE must end with "_concurrency_test".
 *
 *   setup                                  create + migrate + seed roles, print fixture ids
 *   requisitions <n> <start>               create n draft requisitions
 *   vacancies <n> <start>                  create n draft vacancies
 *   submitted-requisition                  create and submit one requisition, print its id
 *   approve <requisitionId> <start>        current approver approves (prints ok / denied)
 *   approved-requisition <positions>       create a fully approved requisition, print its id
 *   allocate <requisitionId> <openings> <start>  create a vacancy from it (ok / denied)
 *   open-vacancy                           create and open one vacancy, print its id
 *   transition <vacancyId> <status> <start>      change status (ok / denied)
 *   applicants <n> <start>                 create n applicants (unique emails)
 *   new-applicant / new-application <vacancyId>   fixtures, print the id
 *   apply-many <vacancyId> <n> <start>     create n applicants and apply each to the vacancy
 *   apply <applicantId> <vacancyId> <start>      one application (ok / denied)
 *   register-same <email> <start>          create an applicant with this email (ok / denied)
 *   advance <applicationId> <expectedStageId> <start>  move to the next stage (ok / denied)
 *   shortlisted-application <vacancyId>    phase 3 fixture: screened + shortlisted, prints {id, stage}
 *   interviewing-application <vacancyId> [completed-panelist]  in Interview (optionally with a completed interview), prints {id, stage}
 *   schedule <applicationId> <panelist> <date> <time> <start>  schedule a 60-minute interview (ok / denied)
 *   completed-interview <applicationId> <panelist>   fixture: past interview marked completed, prints its id
 *   evaluate <interviewId> <username> <draft|submit> <start>  save/submit own scorecard (ok / denied)
 *   timestamp-stability                    write lifecycle timestamps, edit unrelated columns, print before/after
 *   open-vacancy [openings]                (phase 4) optional number of openings
 *   evaluated-application <vacancyId>      phase 4 fixture: in Evaluation with every scorecard submitted, prints its id
 *   selected-application <vacancyId>       evaluated and selected, prints its id
 *   select <applicationId> <start>         Dina selects (ok / denied)
 *   create-offer <applicationId> <start>   Hana drafts an offer (ok / denied; prints the number on success as ok:<number>)
 *   submitted-offer <vacancyId>            fixture: selected + draft + submitted, prints the offer id
 *   approved-offer <vacancyId>             fixture: fully approved, prints the offer id
 *   issued-offer <vacancyId>               fixture: approved and issued, prints the offer id
 *   approve-offer|reject-offer <offerId> <username> <start>  approval decision (ok / denied)
 *   issue-offer <offerId> <start>          Hana issues (ok / denied)
 *   respond-offer <offerId> <accepted|declined> <start>  Hana records the response (ok / denied)
 *   p4-timestamp-stability                 phase 4 lifecycle timestamps vs unrelated edits
 *   accepted-application <vacancyId> [internal|broken]  phase 5 fixture: accepted offer, applicant with education and
 *                                          work history; "internal" links Otto's employee, "broken" attaches a document
 *                                          whose file is missing (conversion fails after the employee was created)
 *   convert <applicationId> [username] <start>  Dina converts (ok:<employee number> / denied); a username also creates a login
 *   p5-timestamp-stability                 conversion timestamps vs unrelated edits
 *   published-vacancy [openings]           phase 7 fixture: open + published on the careers site, prints {id, slug}
 *   portal-candidate                       fixture: an activated careers account, prints its id
 *   portal-apply <accountId> <slug> <start>     apply online with one uploaded PDF (ok:<number> / denied)
 *   portal-withdraw <accountId> <number> <start>  candidate withdraws (ok / denied)
 *   portal-profile <accountId> <n> <start> candidate updates phone + education (ok / denied)
 *   careers-toggle <vacancyId> <publish|unpublish> <start>  HR publishes / unpublishes (ok / denied)
 *   run-reports <from> <to> <start>        phase 8: run every recruitment report (ok:<output hash> / write:<sql>)
 *   checksums                              phase 8: CHECKSUM TABLE of the tables the reports read
 *   report                                 numbers, statuses and counts
 *   teardown                               drop the scratch database
 */

use App\Models\Applicant;
use App\Models\ApplicantAccount;
use App\Models\Application;
use App\Models\ApplicationConversion;
use App\Models\ApplicationEvaluation;
use App\Models\ApplicationInterview;
use App\Models\ApplicationScreening;
use App\Models\ApplicationSelection;
use App\Models\ApplicationStageHistory;
use App\Models\AssessmentType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\EvaluationCriterion;
use App\Models\InterviewPanelist;
use App\Models\InterviewType;
use App\Models\JobOffer;
use App\Models\JobOfferApproval;
use App\Models\JobOfferEvent;
use App\Models\JobRequisition;
use App\Models\JobRequisitionApproval;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Careers\CareersApplicationService;
use App\Services\Careers\PublicVacancyService;
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
use App\Services\Reports\ReportRegistry;
use Database\Seeders\EmployeeMovementTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

$database = (string) getenv('DB_DATABASE');
if (getenv('DB_CONNECTION') !== 'mysql' || ! preg_match('/^[A-Za-z0-9_]+_concurrency_test$/', $database)) {
    fwrite(STDERR, "Refusing to run: DB_CONNECTION must be mysql and DB_DATABASE must end with _concurrency_test.\n");
    exit(2);
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Uploaded files go to a scratch directory, never the real storage/app.
$scratchFiles = sys_get_temp_dir().'/'.$database.'_files';
config(['filesystems.disks.local.root' => $scratchFiles]);

$args = array_slice($argv, 1);
$mode = array_shift($args);

$server = function (string $sql) use ($database) {
    config(['database.connections.mysql.database' => null]);
    DB::purge('mysql');
    DB::connection('mysql')->statement($sql);
    config(['database.connections.mysql.database' => $database]);
    DB::purge('mysql');
};
$waitUntil = function (float $start) {
    while (microtime(true) < $start) {
        usleep(1000);
    }
};
$user = fn (string $username) => User::where('username', $username)->firstOrFail();
$attempt = function (callable $action) {
    try {
        $action();
        echo 'ok';
    } catch (ValidationException $e) {
        echo 'denied: '.collect($e->errors())->flatten()->first();
    }
};
$requisitionData = fn () => [
    'department_id' => Department::value('id'),
    'job_title_id' => JobTitle::value('id'),
    'positions' => 3,
    'reason' => 'new_position',
    'justification' => 'Concurrency test',
    'requested_by_employee_id' => $user('ramon')->employee_id,
];
$shortlisted = function (int $vacancyId, array $applicantData = []) use ($user) {
    $hana = $user('hana');
    $applicant = app(ApplicantService::class)->createApplicant($applicantData + ['first_name' => 'Short', 'last_name' => 'Listed', 'email' => uniqid('sl').'@example.test'], $hana);
    $application = app(ApplicationService::class)->createApplication($applicant, Vacancy::findOrFail($vacancyId), [], $hana);
    $pipeline = app(ApplicationPipelineService::class);
    $pipeline->advance($application, $application->current_vacancy_stage_id, $hana);
    app(ScreeningService::class)->record($application, ['result' => ApplicationScreening::RESULT_PASSED, 'screened_on' => date('Y-m-d')], $hana);

    return $pipeline->shortlist($application, $application->fresh()->current_vacancy_stage_id, $hana)->fresh();
};
$completedInterview = function (Application $application, string $panelist) use ($user) {
    // A past slot (the service doesn't restrict dates; the form request does), unique per call.
    static $day = 0;
    $day++;
    $interview = app(InterviewService::class)->schedule($application, [
        'interview_type_id' => InterviewType::value('id'), 'mode' => 'phone',
        'scheduled_date' => date('Y-m-d', strtotime("-{$day} days")), 'start_time' => '08:00', 'duration_minutes' => 30,
        'panelist_ids' => [$user($panelist)->employee_id],
    ], $user('hana'));

    return app(InterviewService::class)->complete($interview, 'done', $user('hana'));
};
$evaluated = function (int $vacancyId, array $applicantData = []) use ($user, $shortlisted, $completedInterview) {
    $hana = $user('hana');
    $pipeline = app(ApplicationPipelineService::class);
    $application = $shortlisted($vacancyId, $applicantData);
    $application = $pipeline->advance($application, $application->current_vacancy_stage_id, $hana)->fresh();
    $interview = $completedInterview($application, 'eve');
    app(EvaluationService::class)->submit($interview, [
        'recommendation' => 'recommend',
        'scores' => EvaluationCriterion::active()->pluck('id')->map(fn ($c) => ['evaluation_criterion_id' => $c, 'rating' => 4])->all(),
    ], $user('eve'));
    $application = $pipeline->advance($application, $application->current_vacancy_stage_id, $hana)->fresh();
    $assessment = app(AssessmentService::class)->create($application, ['assessment_type_id' => AssessmentType::where('result_type', 'score')->value('id')], $hana);
    app(AssessmentService::class)->complete($assessment, ['score' => 8, 'maximum_score' => 10], $hana);

    return $pipeline->advance($application, $application->current_vacancy_stage_id, $hana)->fresh();
};
$offerTerms = fn () => [
    'job_title_id' => JobTitle::value('id'),
    'proposed_start_date' => date('Y-m-d', strtotime('+30 days')),
    'expiry_date' => date('Y-m-d', strtotime('+10 days')),
    'base_salary' => 50000,
    'salary_frequency' => 'monthly',
    'currency' => 'PHP',
];
$selectedApp = function (int $vacancyId, array $applicantData = []) use ($evaluated, $user) {
    $application = $evaluated($vacancyId, $applicantData);
    app(SelectionService::class)->decide($application, ApplicationSelection::SELECTED, 'fixture', $user('dina'));

    return $application;
};
$offerAt = function (int $vacancyId, string $state, array $applicantData = []) use ($selectedApp, $offerTerms, $user) {
    $offer = app(OfferService::class)->createDraft($selectedApp($vacancyId, $applicantData), $offerTerms(), $user('hana'));
    $offer = app(OfferService::class)->submit($offer, null, $user('hana'));
    if ($state === 'submitted') {
        return $offer;
    }
    app(OfferApprovalService::class)->approve($offer, $user('mona'));
    $offer = app(OfferApprovalService::class)->approve($offer, $user('dina'));

    if (in_array($state, ['issued', 'accepted'], true)) {
        $offer = app(OfferService::class)->issue($offer, null, $user('hana'));
    }

    return $state === 'accepted' ? app(OfferService::class)->respond($offer, 'accepted', null, $user('hana')) : $offer;
};
// Phase 5: an applicant with history, so duplicate copies would show.
$historyData = fn () => [
    'education' => [
        ['level' => 'College', 'institute' => 'State University', 'degree' => 'BSIT', 'start_date' => '2010-06-01', 'end_date' => '2014-04-01', 'is_completed' => true],
        ['level' => 'Masters', 'institute' => 'Tech Institute', 'degree' => 'MSCS', 'start_date' => '2015-06-01', 'end_date' => '2017-04-01', 'is_completed' => true],
    ],
    'work_experience' => [
        ['company' => 'Alpha', 'job_title' => 'Dev', 'from' => '2014-05-01', 'to' => '2018-12-31'],
        ['company' => 'Beta', 'job_title' => 'Senior Dev', 'from' => '2019-01-01', 'to' => '2025-12-31'],
    ],
];
$vacancyData = fn (array $extra = []) => $extra + [
    'job_title_id' => JobTitle::value('id'),
    'department_id' => Department::value('id'),
    'openings' => 1,
    'visibility' => 'internal',
];

switch ($mode) {
    case 'setup':
        $server("CREATE DATABASE IF NOT EXISTS `{$database}`");
        Artisan::call('migrate:fresh', ['--force' => true]);
        (new RolesAndPermissionsSeeder)->run();
        (new EmployeeMovementTypeSeeder)->run();

        Department::create(['name' => 'Engineering', 'shortcut' => 'ENG']);
        JobTitle::create(['job_title' => 'Engineer']);
        $person = function (string $username, string $role, ?int $supervisorId) {
            $employee = Employee::create(['employee_number' => strtoupper($username), 'emp_first_name' => ucfirst($username), 'emp_last_name' => 'Test', 'emp_sex' => 'other', 'status' => 'active', 'supervisor_id' => $supervisorId]);
            User::create(['username' => $username, 'password' => bcrypt('x'), 'status' => 'active', 'employee_id' => $employee->id])->assignRole($role);

            return $employee->id;
        };
        $dina = $person('dina', 'hr_director', null);
        $maya = $person('maya', 'supervisor', $dina);
        $person('ramon', 'supervisor', $maya);
        $person('hana', 'hr_staff', null);
        $person('eve', 'employee', null);
        $person('otto', 'supervisor', null);
        // Offer approvals start from the submitter's supervisor: Hana → Mona → Dina.
        $mona = $person('mona', 'hr_manager', $dina);
        Employee::where('employee_number', 'HANA')->update(['supervisor_id' => $mona]);
        echo json_encode(['ok' => true]);
        break;

    case 'requisitions':
        [$count, $start] = [(int) $args[0], (float) $args[1]];
        $waitUntil($start);
        $numbers = [];
        for ($i = 0; $i < $count; $i++) {
            $numbers[] = app(RequisitionService::class)->createRequisition($requisitionData(), $user('ramon'))->requisition_number;
        }
        echo json_encode($numbers);
        break;

    case 'vacancies':
        [$count, $start] = [(int) $args[0], (float) $args[1]];
        $waitUntil($start);
        $numbers = [];
        for ($i = 0; $i < $count; $i++) {
            $numbers[] = app(VacancyService::class)->createVacancy($vacancyData(), $user('hana'))->vacancy_number;
        }
        echo json_encode($numbers);
        break;

    case 'submitted-requisition':
        $req = app(RequisitionService::class)->createRequisition($requisitionData(), $user('ramon'));
        echo app(RequisitionService::class)->submit($req, $user('ramon'))->id;
        break;

    case 'approve':
        [$id, $start] = [(int) $args[0], (float) $args[1]];
        $req = JobRequisition::findOrFail($id);
        $waitUntil($start);
        $attempt(fn () => app(RequisitionApprovalService::class)->approve($req, $user('maya')));
        break;

    case 'approved-requisition':
        $req = app(RequisitionService::class)->createRequisition(['positions' => (int) $args[0]] + $requisitionData(), $user('ramon'));
        app(RequisitionService::class)->submit($req, $user('ramon'));
        app(RequisitionApprovalService::class)->approve($req, $user('maya'));
        echo app(RequisitionApprovalService::class)->approve($req, $user('dina'))->id;
        break;

    case 'allocate':
        [$id, $openings, $start] = [(int) $args[0], (int) $args[1], (float) $args[2]];
        $waitUntil($start);
        $attempt(fn () => app(VacancyService::class)->createVacancy($vacancyData(['job_requisition_id' => $id, 'openings' => $openings]), $user('hana')));
        break;

    case 'open-vacancy':
        $vacancy = app(VacancyService::class)->createVacancy($vacancyData(isset($args[0]) ? ['openings' => (int) $args[0]] : []), $user('hana'));
        echo app(VacancyService::class)->transition($vacancy, Vacancy::STATUS_OPEN, $user('hana'))->id;
        break;

    case 'transition':
        [$id, $status, $start] = [(int) $args[0], $args[1], (float) $args[2]];
        $vacancy = Vacancy::findOrFail($id);
        $waitUntil($start);
        $attempt(fn () => app(VacancyService::class)->transition($vacancy, $status, $user('dina'), 'race'));
        break;

    case 'applicants':
        [$count, $start] = [(int) $args[0], (float) $args[1]];
        $waitUntil($start);
        $numbers = [];
        for ($i = 0; $i < $count; $i++) {
            $numbers[] = app(ApplicantService::class)->createApplicant(['first_name' => 'P'.getmypid(), 'last_name' => "N{$i}", 'email' => getmypid()."-{$i}@example.test"], $user('hana'))->applicant_number;
        }
        echo json_encode($numbers);
        break;

    case 'new-applicant':
        echo app(ApplicantService::class)->createApplicant(['first_name' => 'Solo', 'last_name' => 'Applicant', 'email' => uniqid('solo').'@example.test'], $user('hana'))->id;
        break;

    case 'new-application':
        $applicant = app(ApplicantService::class)->createApplicant(['first_name' => 'Move', 'last_name' => 'Me', 'email' => uniqid('move').'@example.test'], $user('hana'));
        $application = app(ApplicationService::class)->createApplication($applicant, Vacancy::findOrFail((int) $args[0]), [], $user('hana'));
        echo json_encode(['id' => $application->id, 'stage' => $application->current_vacancy_stage_id]);
        break;

    case 'apply-many':
        [$vacancyId, $count, $start] = [(int) $args[0], (int) $args[1], (float) $args[2]];
        $applicants = [];
        for ($i = 0; $i < $count; $i++) {
            $applicants[] = app(ApplicantService::class)->createApplicant(['first_name' => 'A'.getmypid(), 'last_name' => "N{$i}", 'email' => 'a'.getmypid()."-{$i}@example.test"], $user('hana'));
        }
        $waitUntil($start);
        $numbers = [];
        foreach ($applicants as $applicant) {
            $numbers[] = app(ApplicationService::class)->createApplication($applicant, Vacancy::findOrFail($vacancyId), [], $user('hana'))->application_number;
        }
        echo json_encode($numbers);
        break;

    case 'apply':
        [$applicantId, $vacancyId, $start] = [(int) $args[0], (int) $args[1], (float) $args[2]];
        $applicant = Applicant::findOrFail($applicantId);
        $vacancy = Vacancy::findOrFail($vacancyId);
        $waitUntil($start);
        $attempt(fn () => app(ApplicationService::class)->createApplication($applicant, $vacancy, [], $user('hana')));
        break;

    case 'register-same':
        [$email, $start] = [$args[0], (float) $args[1]];
        $waitUntil($start);
        $attempt(fn () => app(ApplicantService::class)->createApplicant(['first_name' => 'Same', 'last_name' => 'Person', 'email' => $email], $user('hana')));
        break;

    case 'advance':
        [$id, $expected, $start] = [(int) $args[0], (int) $args[1], (float) $args[2]];
        $application = Application::findOrFail($id);
        $waitUntil($start);
        $attempt(fn () => app(ApplicationPipelineService::class)->advance($application, $expected, $user('hana')));
        break;

    case 'shortlisted-application':
        $application = $shortlisted((int) $args[0]);
        echo json_encode(['id' => $application->id, 'stage' => $application->current_vacancy_stage_id]);
        break;

    case 'interviewing-application':
        $application = $shortlisted((int) $args[0]);
        $application = app(ApplicationPipelineService::class)->advance($application, $application->current_vacancy_stage_id, $user('hana'))->fresh();
        if (isset($args[1])) {
            $completedInterview($application, $args[1]);
        }
        echo json_encode(['id' => $application->id, 'stage' => $application->current_vacancy_stage_id]);
        break;

    case 'schedule':
        [$id, $panelist, $date, $time, $start] = [(int) $args[0], $args[1], $args[2], $args[3], (float) $args[4]];
        $application = Application::findOrFail($id);
        $data = [
            'interview_type_id' => InterviewType::value('id'), 'mode' => 'phone',
            'scheduled_date' => $date, 'start_time' => $time, 'duration_minutes' => 60,
            'panelist_ids' => [$user($panelist)->employee_id],
        ];
        $waitUntil($start);
        $attempt(fn () => app(InterviewService::class)->schedule($application, $data, $user('hana')));
        break;

    case 'completed-interview':
        echo $completedInterview(Application::findOrFail((int) $args[0]), $args[1])->id;
        break;

    case 'evaluate':
        [$id, $username, $mode, $start] = [(int) $args[0], $args[1], $args[2], (float) $args[3]];
        $interview = ApplicationInterview::findOrFail($id);
        $evaluator = $user($username);
        $data = [
            'recommendation' => 'recommend',
            'comments' => 'by '.getmypid(),
            'scores' => EvaluationCriterion::active()->pluck('id')->map(fn ($c) => ['evaluation_criterion_id' => $c, 'rating' => 4])->all(),
        ];
        $waitUntil($start);
        $attempt(fn () => $mode === 'submit'
            ? app(EvaluationService::class)->submit($interview, $data, $evaluator)
            : app(EvaluationService::class)->saveDraft($interview, $data, $evaluator));
        break;

    case 'timestamp-stability':
        // Lifecycle timestamps must never be rewritten by the database when unrelated columns change.
        $vacancy = app(VacancyService::class)->transition(app(VacancyService::class)->createVacancy($vacancyData(), $user('hana')), Vacancy::STATUS_OPEN, $user('hana'));
        $application = $shortlisted($vacancy->id);
        $application = app(ApplicationPipelineService::class)->advance($application, $application->current_vacancy_stage_id, $user('hana'))->fresh();
        $cancelled = app(InterviewService::class)->schedule($application, [
            'interview_type_id' => InterviewType::value('id'), 'mode' => 'phone',
            'scheduled_date' => date('Y-m-d', strtotime('+3 days')), 'start_time' => '09:00', 'duration_minutes' => 30,
            'panelist_ids' => [$user('otto')->employee_id],
        ], $user('hana'));
        app(InterviewService::class)->cancel($cancelled, 'x', $user('hana'));
        $completed = $completedInterview($application, 'eve');
        app(EvaluationService::class)->submit($completed, [
            'recommendation' => 'neutral',
            'scores' => EvaluationCriterion::active()->pluck('id')->map(fn ($c) => ['evaluation_criterion_id' => $c, 'rating' => 3])->all(),
        ], $user('eve'));
        $application = app(ApplicationPipelineService::class)->advance($application, $application->current_vacancy_stage_id, $user('hana'))->fresh();
        $assessment = app(AssessmentService::class)->create($application, ['assessment_type_id' => AssessmentType::where('result_type', 'score')->value('id'), 'scheduled_at' => date('Y-m-d 10:00:00')], $user('hana'));
        app(AssessmentService::class)->complete($assessment, ['score' => 7, 'maximum_score' => 10], $user('hana'));
        $openAssessment = app(AssessmentService::class)->create($application, ['assessment_type_id' => AssessmentType::where('result_type', 'score')->value('id'), 'scheduled_at' => date('Y-m-d 11:00:00')], $user('hana'));
        app(AssessmentService::class)->cancel($openAssessment, 'x', $user('hana'));
        app(InterviewService::class)->reschedule(app(InterviewService::class)->schedule($application, [
            'interview_type_id' => InterviewType::value('id'), 'mode' => 'phone',
            'scheduled_date' => date('Y-m-d', strtotime('+5 days')), 'start_time' => '09:00', 'duration_minutes' => 30,
            'panelist_ids' => [$user('otto')->employee_id],
        ], $user('hana')), ['scheduled_date' => date('Y-m-d', strtotime('+6 days')), 'start_time' => '09:00', 'duration_minutes' => 30], $user('hana'));

        $columns = [
            'application_interviews' => ['starts_at', 'ends_at', 'completed_at', 'cancelled_at'],
            'interview_reschedules' => ['previous_starts_at', 'previous_ends_at', 'new_starts_at', 'new_ends_at', 'rescheduled_at'],
            'application_assessments' => ['scheduled_at', 'started_at', 'completed_at', 'cancelled_at'],
            'application_evaluations' => ['submitted_at'],
        ];
        $read = fn () => collect($columns)->map(fn ($cols, $table) => DB::table($table)->orderBy('id')->get(['id', ...$cols])->toArray())->all();
        $before = $read();
        sleep(2);
        // Unrelated edits, straight through the query builder so no model guard or cast is involved.
        DB::table('application_interviews')->update(['instructions' => 'edited', 'location' => 'Room 9']);
        DB::table('interview_reschedules')->update(['reason' => 'edited']);
        DB::table('application_assessments')->update(['remarks' => 'edited']);
        DB::table('application_evaluations')->update(['comments' => 'edited']);
        echo json_encode(['before' => $before, 'after' => $read()]);
        break;

    case 'evaluated-application':
        echo $evaluated((int) $args[0])->id;
        break;

    case 'selected-application':
        echo $selectedApp((int) $args[0])->id;
        break;

    case 'select':
        [$id, $start] = [(int) $args[0], (float) $args[1]];
        $application = Application::findOrFail($id);
        $waitUntil($start);
        $attempt(fn () => app(SelectionService::class)->decide($application, ApplicationSelection::SELECTED, 'race', $user('dina')));
        break;

    case 'create-offer':
        [$id, $start] = [(int) $args[0], (float) $args[1]];
        $application = Application::findOrFail($id);
        $terms = $offerTerms();
        $waitUntil($start);
        try {
            echo 'ok:'.app(OfferService::class)->createDraft($application, $terms, $user('hana'))->offer_number;
        } catch (ValidationException $e) {
            echo 'denied: '.collect($e->errors())->flatten()->first();
        }
        break;

    case 'submitted-offer':
    case 'approved-offer':
    case 'issued-offer':
        echo $offerAt((int) $args[0], str_replace('-offer', '', $mode))->id;
        break;

    case 'approve-offer':
    case 'reject-offer':
        [$id, $username, $start] = [(int) $args[0], $args[1], (float) $args[2]];
        $offer = JobOffer::findOrFail($id);
        $actor = $user($username);
        $waitUntil($start);
        $attempt(fn () => $mode === 'approve-offer'
            ? app(OfferApprovalService::class)->approve($offer, $actor)
            : app(OfferApprovalService::class)->reject($offer, $actor, 'race'));
        break;

    case 'issue-offer':
        [$id, $start] = [(int) $args[0], (float) $args[1]];
        $offer = JobOffer::findOrFail($id);
        $waitUntil($start);
        $attempt(fn () => app(OfferService::class)->issue($offer, null, $user('hana')));
        break;

    case 'respond-offer':
        [$id, $response, $start] = [(int) $args[0], $args[1], (float) $args[2]];
        $offer = JobOffer::findOrFail($id);
        $waitUntil($start);
        $attempt(fn () => app(OfferService::class)->respond($offer, $response, "race {$response}", $user('hana')));
        break;

    case 'accepted-application':
        [$vacancyId, $variant] = [(int) $args[0], $args[1] ?? null];
        $data = $historyData() + ($variant === 'internal' ? ['is_internal' => true, 'employee_id' => $user('otto')->employee_id] : []);
        $offer = $offerAt($vacancyId, 'accepted', $data);
        if ($variant === 'broken') {
            $applicant = Applicant::findOrFail($offer->applicant_id);
            $document = $applicant->documents()->create([
                'applicant_document_type_id' => DB::table('applicant_document_types')->where('code', 'resume')->value('id'),
                'original_name' => 'missing.pdf', 'disk' => 'local', 'file_path' => 'applicant-documents/does-not-exist-'.uniqid().'.pdf',
                'mime_type' => 'application/pdf', 'file_size' => 1, 'uploaded_at' => now(),
            ]);
            DB::table('application_documents')->insert(['application_id' => $offer->application_id, 'applicant_document_id' => $document->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        echo $offer->application_id;
        break;

    case 'convert':
        // convert <applicationId> [username] <start>: the start time is always last (race() appends it).
        [$id, $username, $start] = count($args) === 3 ? [(int) $args[0], $args[1], (float) $args[2]] : [(int) $args[0], null, (float) $args[1]];
        $application = Application::findOrFail($id);
        $documentIds = DB::table('application_documents')->where('application_id', $id)->pluck('applicant_document_id')->all();
        $data = ['emp_sex' => 'other', 'document_ids' => $documentIds]
            + ($username ? ['create_user_account' => true, 'username' => $username, 'password' => 'Race!Passw0rd'] : []);
        $waitUntil($start);
        try {
            echo 'ok:'.app(ApplicationConversionService::class)->convert($application, $data, $user('dina'))->employee_number;
        } catch (ValidationException $e) {
            echo 'denied: '.collect($e->errors())->flatten()->first();
        }
        break;

    case 'published-vacancy':
        $vacancy = app(VacancyService::class)->createVacancy($vacancyData(['visibility' => 'external', 'openings' => (int) ($args[0] ?? 1)]), $user('hana'));
        $vacancy = app(VacancyService::class)->transition($vacancy, Vacancy::STATUS_OPEN, $user('hana'));
        $vacancy = app(PublicVacancyService::class)->publish($vacancy, $user('hana'));
        echo json_encode(['id' => $vacancy->id, 'slug' => $vacancy->public_slug]);
        break;

    case 'portal-candidate':
        $email = uniqid('cand').'@example.test';
        $applicant = app(ApplicantService::class)->createApplicant(['first_name' => 'Portal', 'last_name' => 'Candidate', 'email' => $email], null);
        echo ApplicantAccount::create(['applicant_id' => $applicant->id, 'email' => $email, 'password' => 'Secret-pass-123', 'email_verified_at' => now(), 'status' => 'active'])->id;
        break;

    case 'portal-apply':
        [$accountId, $slug, $start] = [(int) $args[0], $args[1], (float) $args[2]];
        $account = ApplicantAccount::findOrFail($accountId);
        $tmp = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($tmp, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $upload = ['applicant_document_type_id' => DB::table('applicant_document_types')->where('code', 'resume')->value('id'), 'file' => new UploadedFile($tmp, 'cv.pdf', 'application/pdf', null, true)];
        $waitUntil($start);
        try {
            echo 'ok:'.app(CareersApplicationService::class)->submit($account, $slug, ['uploads' => [$upload], 'cover_note' => 'race'])->application_number;
        } catch (ValidationException $e) {
            echo 'denied: '.collect($e->errors())->flatten()->first();
        }
        break;

    case 'portal-withdraw':
        [$accountId, $number, $start] = [(int) $args[0], $args[1], (float) $args[2]];
        $account = ApplicantAccount::findOrFail($accountId);
        $waitUntil($start);
        $attempt(fn () => app(CareersApplicationService::class)->withdraw($account, $number, 'race'));
        break;

    case 'portal-profile':
        [$accountId, $n, $start] = [(int) $args[0], $args[1], (float) $args[2]];
        $account = ApplicantAccount::findOrFail($accountId);
        $waitUntil($start);
        $attempt(fn () => app(CareersApplicationService::class)->updateProfile($account, [
            'phone' => '0917 55'.str_pad($n, 5, '0', STR_PAD_LEFT),
            'address' => "Street {$n}",
            'education' => [['institute' => "School {$n}"]],
            'work_experience' => [],
        ]));
        break;

    case 'careers-toggle':
        [$id, $action, $start] = [(int) $args[0], $args[1], (float) $args[2]];
        $vacancy = Vacancy::findOrFail($id);
        $waitUntil($start);
        $attempt(fn () => $action === 'publish'
            ? app(PublicVacancyService::class)->publish($vacancy, $user('hana'))
            : app(PublicVacancyService::class)->unpublish($vacancy, $user('hana')));
        break;

    case 'p5-timestamp-stability':
        $vacancy = app(VacancyService::class)->transition(app(VacancyService::class)->createVacancy($vacancyData(['openings' => 1]), $user('hana')), Vacancy::STATUS_OPEN, $user('hana'));
        $offer = $offerAt($vacancy->id, 'accepted', $historyData());
        app(ApplicationConversionService::class)->convert(Application::findOrFail($offer->application_id), ['emp_sex' => 'other'], $user('dina'));
        $read = fn () => DB::table('application_conversions')->orderBy('id')->get(['id', 'converted_at', 'start_date'])->toArray();
        $before = $read();
        sleep(2);
        DB::table('application_conversions')->update(['notices' => DB::raw("JSON_ARRAY('edited')"), 'documents_copied' => 0]);
        echo json_encode(['before' => $before, 'after' => $read()]);
        break;

    case 'p4-timestamp-stability':
        $vacancy = app(VacancyService::class)->transition(app(VacancyService::class)->createVacancy($vacancyData(['openings' => 4]), $user('hana')), Vacancy::STATUS_OPEN, $user('hana'));
        $offerAt($vacancy->id, 'issued');
        $accepted = $offerAt($vacancy->id, 'issued');
        app(OfferService::class)->respond($accepted, 'accepted', null, $user('hana'));
        $withdrawn = $offerAt($vacancy->id, 'submitted');
        app(OfferService::class)->withdraw($withdrawn, 'x', $user('hana'));
        $rejected = $offerAt($vacancy->id, 'submitted');
        app(OfferApprovalService::class)->reject($rejected, $user('mona'), 'x');
        // An expired offer: issued, then the expiry date moved into the past (raw, bypassing the term guard), then expired.
        $expiring = JobOffer::where('status', JobOffer::STATUS_ISSUED)->firstOrFail();
        DB::table('job_offers')->where('id', $expiring->id)->update(['expiry_date' => date('Y-m-d', strtotime('-1 day')), 'offer_date' => date('Y-m-d', strtotime('-2 days'))]);
        app(OfferService::class)->expireIfDue($expiring);

        $columns = [
            'application_selections' => ['decided_at'],
            'job_offers' => ['submitted_at', 'approved_at', 'rejected_at', 'issued_at', 'responded_at', 'expired_at', 'withdrawn_at'],
            'job_offer_approvals' => ['acted_at'],
            'job_offer_events' => ['occurred_at'],
        ];
        $read = fn () => collect($columns)->map(fn ($cols, $table) => DB::table($table)->orderBy('id')->get(['id', ...$cols])->toArray())->all();
        $before = $read();
        sleep(2);
        DB::table('application_selections')->update(['remarks' => 'edited']);
        DB::table('job_offers')->update(['remarks' => 'edited', 'response_remarks' => DB::raw('response_remarks')]);
        DB::table('job_offer_approvals')->update(['remarks' => 'edited']);
        DB::table('job_offer_events')->update(['remarks' => 'edited']);
        echo json_encode(['before' => $before, 'after' => $read(), 'statuses' => JobOffer::orderBy('id')->pluck('status')]);
        break;

    case 'schema-onupdate':
        // Columns MySQL/MariaDB would silently overwrite on every row update.
        $tables = ['employee_documents', 'applicant_documents', 'applications', 'application_stage_histories', 'application_screenings', 'job_requisitions', 'job_requisition_approvals', 'vacancies',
            'interview_types', 'assessment_types', 'evaluation_criteria', 'application_interviews', 'interview_panelists', 'interview_reschedules',
            'application_assessments', 'application_evaluations', 'application_evaluation_scores',
            'application_selections', 'job_offers', 'job_offer_approvals', 'job_offer_events',
            'application_conversions', 'application_conversion_documents',
            'applicant_accounts', 'applicant_password_reset_tokens', 'careers_settings', 'recruitment_portal_events', 'applicant_document_types'];
        echo json_encode(collect(DB::select('select table_name as t, column_name as c from information_schema.columns where table_schema = database() and extra like ?', ['%on update%']))
            ->filter(fn ($r) => in_array($r->t, $tables, true))->map(fn ($r) => "{$r->t}.{$r->c}")->values());
        break;

    case 'run-reports':
        // Phase 8: every recruitment report (summary + all rows) for a period;
        // prints ok:<hash of the output> or write:<first non-SELECT statement>.
        [$from, $to, $start] = [$args[0], $args[1], (float) $args[2]];
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (! preg_match('/^\s*select\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $waitUntil($start);
        $output = [];
        foreach (ReportRegistry::REPORTS as $class) {
            if ($class::category() !== 'recruitment') {
                continue;
            }
            $report = ReportRegistry::resolve($class::key());
            $filters = $report->normalize(['date_from' => $from, 'date_to' => $to]);
            $output[$class::key()] = [$report->summary($filters), $report->hasRows($filters) ? iterator_to_array($report->rows($filters), false) : []];
        }
        echo $writes ? 'write:'.$writes[0] : 'ok:'.md5(json_encode($output));
        break;

    case 'checksums':
        // Phase 8: CHECKSUM TABLE of everything the recruitment reports read.
        $tables = ['vacancies', 'job_requisitions', 'applications', 'application_stage_histories', 'application_screenings', 'application_interviews',
            'interview_panelists', 'interview_reschedules', 'application_assessments', 'application_evaluations', 'application_selections', 'job_offers',
            'application_conversions', 'recruitment_portal_events', 'employees', 'users', 'departments'];
        echo json_encode(collect($tables)->mapWithKeys(fn ($t) => [$t => DB::selectOne("CHECKSUM TABLE `{$t}`")->Checksum])->all());
        break;

    case 'report':
        echo json_encode([
            'requisition_numbers' => JobRequisition::orderBy('id')->pluck('requisition_number'),
            'vacancy_numbers' => Vacancy::orderBy('id')->pluck('vacancy_number'),
            'approvals' => JobRequisitionApproval::orderBy('id')->get(['job_requisition_id', 'approval_order', 'status', 'acted_by'])->toArray(),
            'vacancies' => Vacancy::orderBy('id')->get(['id', 'job_requisition_id', 'openings', 'filled_count', 'status'])->toArray(),
            'applicant_numbers' => Applicant::orderBy('id')->pluck('applicant_number'),
            'applicant_emails' => Applicant::orderBy('id')->pluck('normalized_email'),
            'applications' => Application::orderBy('id')->get(['id', 'applicant_id', 'vacancy_id', 'application_number', 'current_vacancy_stage_id', 'status'])->toArray(),
            'applicant_documents' => DB::table('applicant_documents')->orderBy('id')->get(['id', 'applicant_id', 'file_path'])->toArray(),
            'application_documents' => DB::table('application_documents')->count(),
            'stored_files' => count(Storage::disk('local')->allFiles('recruitment')),
            'applicants' => DB::table('applicants')->orderBy('id')->get(['id', 'phone', 'phone_key', 'address'])->toArray(),
            'applicant_educations' => DB::table('applicant_educations')->get(['applicant_id', 'institute'])->toArray(),
            'portal_events' => DB::table('recruitment_portal_events')->orderBy('id')->get(['event', 'application_id', 'vacancy_id'])->toArray(),
            'vacancy_publication' => Vacancy::orderBy('id')->get(['id', 'status', 'published_at', 'public_slug'])->toArray(),
            'history' => ApplicationStageHistory::orderBy('id')->get(['application_id', 'action', 'to_stage_name'])->toArray(),
            'interviews' => ApplicationInterview::orderBy('id')->get(['id', 'application_id', 'status', 'starts_at', 'ends_at'])->toArray(),
            'panelists' => InterviewPanelist::orderBy('id')->get(['application_interview_id', 'employee_id'])->toArray(),
            'evaluations' => ApplicationEvaluation::orderBy('id')->get(['id', 'application_interview_id', 'evaluator_employee_id', 'status', 'comments'])->toArray(),
            'evaluation_scores' => DB::table('application_evaluation_scores')->count(),
            'selections' => ApplicationSelection::orderBy('id')->get(['application_id', 'decision'])->toArray(),
            'offers' => JobOffer::orderBy('id')->get(['id', 'application_id', 'offer_number', 'status', 'response'])->toArray(),
            'offer_approvals' => JobOfferApproval::orderBy('id')->get(['job_offer_id', 'approval_order', 'status', 'acted_by'])->toArray(),
            'offer_events' => JobOfferEvent::orderBy('id')->get(['job_offer_id', 'event'])->toArray(),
            'conversions' => ApplicationConversion::orderBy('id')->get(['application_id', 'employee_id', 'employee_number', 'conversion_type', 'employee_movement_id', 'user_account', 'user_id'])->toArray(),
            'employees' => Employee::orderBy('id')->get(['id', 'employee_number', 'emp_first_name'])->toArray(),
            'employee_sequence_next' => (int) DB::table('number_sequences')->where('key', 'employee_number')->value('next_number'),
            'hiring_movements' => EmployeeMovement::whereHas('type', fn ($q) => $q->where('code', 'hiring'))->get(['employee_id'])->toArray(),
            'educations' => DB::table('educations')->get(['employee_id', 'institute'])->toArray(),
            'work_experiences' => DB::table('work_experiences')->get(['employee_id', 'company'])->toArray(),
            'users' => User::orderBy('id')->get(['username', 'employee_id'])->toArray(),
        ]);
        break;

    case 'teardown':
        $server("DROP DATABASE IF EXISTS `{$database}`");
        File::deleteDirectory($scratchFiles);
        echo json_encode(['ok' => true]);
        break;

    default:
        fwrite(STDERR, "Unknown mode.\n");
        exit(2);
}
