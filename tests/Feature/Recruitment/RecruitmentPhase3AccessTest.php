<?php

namespace Tests\Feature\Recruitment;

use App\Models\ApplicantDocumentType;
use App\Models\AssessmentType;
use App\Models\EvaluationCriterion;
use App\Models\InterviewType;
use App\Models\RecruitmentSource;
use App\Services\Recruitment\ApplicantDocumentService;
use App\Services\Recruitment\ApplicationService;
use App\Services\Recruitment\InterviewService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 3 permissions and their rollout, settings for the new lookups, and
 * panelist access to submitted documents.
 */
class RecruitmentPhase3AccessTest extends RecruitmentTestCase
{
    private const PHASE3 = [
        'recruitment.interview.view', 'recruitment.interview.create', 'recruitment.interview.update',
        'recruitment.assessment.view', 'recruitment.assessment.create', 'recruitment.assessment.update',
        'recruitment.evaluation.view',
    ];

    private function phase3Of(string $role): array
    {
        return Role::findByName($role)->permissions->pluck('name')->intersect(self::PHASE3)->sort()->values()->all();
    }

    #[Test]
    public function roles_receive_the_intended_phase3_permissions(): void
    {
        $all = collect(self::PHASE3)->sort()->values()->all();

        foreach (['superadmin', 'hr_director', 'hr_manager', 'hr_staff'] as $role) {
            $this->assertSame($all, $this->phase3Of($role), $role);
        }
        foreach (['supervisor', 'employee', 'payroll'] as $role) {
            $this->assertSame([], $this->phase3Of($role), $role);
        }
        $this->assertSame(7, Permission::where('name', 'like', 'recruitment.interview.%')->orWhere('name', 'like', 'recruitment.assessment.%')->orWhere('name', 'like', 'recruitment.evaluation.%')->count());
    }

    #[Test]
    public function the_migration_and_seeder_are_idempotent_and_never_revoke(): void
    {
        Role::findByName('hr_staff')->revokePermissionTo('recruitment.interview.update'); // a company customization
        $before = Permission::count();
        $migration = require database_path('migrations/2026_10_06_000006_add_recruitment_phase3_permissions.php');

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertSame($before, Permission::count());
        $this->assertFalse(Role::findByName('hr_staff')->hasPermissionTo('recruitment.interview.update'), 'seeder keeps customizations');

        $unrelated = Role::all()->mapWithKeys(fn ($r) => [$r->name => $r->permissions->pluck('name')->diff(self::PHASE3)->sort()->values()->all()]);
        $migration->up();
        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertSame($before, Permission::count());
        $this->assertEquals($unrelated, Role::all()->mapWithKeys(fn ($r) => [$r->name => $r->fresh()->permissions->pluck('name')->diff(self::PHASE3)->sort()->values()->all()]));
    }

    #[Test]
    public function employees_and_payroll_get_no_recruitment_administration(): void
    {
        $app = $this->inInterview($this->openVacancy());
        $interview = $this->scheduleInterview($app, [$this->maya]);
        $employee = $this->userWithRole('employee');
        $payroll = $this->userWithRole('payroll');

        foreach ([$employee, $payroll] as $user) {
            $this->actingAs($user)->get(route('recruitment.interviews.show', $interview))->assertForbidden();
            $this->actingAs($user)->post(route('recruitment.applications.interviews.store', $app), $this->interviewPayload([$this->dina]))->assertForbidden();
            $this->actingAs($user)->post(route('recruitment.interviews.cancel', $interview))->assertForbidden();
            $this->actingAs($user)->post(route('recruitment.settings.interview-types.store'), ['name' => 'x'])->assertForbidden();
            $this->actingAs($user)->get(route('recruitment.interviews.mine'))->assertOk()
                ->assertInertia(fn (AssertableInertia $p) => $p->has('interviews.data', 0)->where('recruitmentAccess.myInterviews', false));
        }
    }

    #[Test]
    public function settings_manage_interview_types_assessment_types_and_criteria(): void
    {
        $this->actingAs($this->hrStaff)->post(route('recruitment.settings.criteria.store'), ['name' => 'Culture add'])->assertForbidden();

        $this->actingAs($this->dina)->get(route('recruitment.settings.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('interviewTypes', 5)->has('assessmentTypes', 5)->has('criteria', 6)->has('ratingScale', 5));

        $this->actingAs($this->dina)->post(route('recruitment.settings.interview-types.store'), ['name' => 'Culture Interview', 'description' => 'Values fit'])->assertSessionHasNoErrors();
        $this->actingAs($this->dina)->post(route('recruitment.settings.assessment-types.store'), ['name' => 'Case Study', 'result_type' => 'pass_fail'])->assertSessionHasNoErrors();
        $this->actingAs($this->dina)->post(route('recruitment.settings.assessment-types.store'), ['name' => 'Bad', 'result_type' => 'letter_grade'])->assertSessionHasErrors('result_type');
        $this->actingAs($this->dina)->post(route('recruitment.settings.criteria.store'), ['name' => 'Culture add'])->assertSessionHasNoErrors();

        $this->assertSame(['culture_interview', 'Values fit'], [InterviewType::where('name', 'Culture Interview')->value('code'), InterviewType::where('name', 'Culture Interview')->value('description')]);
        $this->assertSame('pass_fail', AssessmentType::where('code', 'case_study')->value('result_type'));
        $this->assertTrue(EvaluationCriterion::where('code', 'culture_add')->value('is_active'));

        // Deactivating hides a type from new interviews; codes never change.
        $type = InterviewType::where('code', 'culture_interview')->first();
        $this->actingAs($this->dina)->put(route('recruitment.settings.interview-types.update', $type), ['name' => 'Culture Fit', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame(['culture_interview', 'Culture Fit', false], [$type->fresh()->code, $type->fresh()->name, $type->fresh()->is_active]);

        // Fields a lookup doesn't have are ignored.
        $source = RecruitmentSource::first();
        $this->actingAs($this->dina)->put(route('recruitment.settings.sources.update', $source), ['name' => $source->name, 'result_type' => 'score'])->assertSessionHasNoErrors();
    }

    #[Test]
    public function panelists_can_open_submitted_documents_of_their_interview_only(): void
    {
        Storage::fake('local');
        $vacancy = $this->openVacancy();
        $eve = $this->userWithRole('employee');
        $type = ApplicantDocumentType::where('code', 'resume')->first();

        $mine = $this->inInterview($vacancy);
        $theirs = $this->inInterview($vacancy);
        $docs = app(ApplicantDocumentService::class);
        $attached = $docs->upload($mine->applicant, UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'), ['applicant_document_type_id' => $type->id], $this->hrStaff);
        $notAttached = $docs->upload($mine->applicant, UploadedFile::fake()->create('payslip.pdf', 20, 'application/pdf'), ['applicant_document_type_id' => $type->id], $this->hrStaff);
        $otherApplicants = $docs->upload($theirs->applicant, UploadedFile::fake()->create('other.pdf', 20, 'application/pdf'), ['applicant_document_type_id' => $type->id], $this->hrStaff);
        app(ApplicationService::class)->attachDocuments($mine, [$attached->id], $this->hrStaff);
        app(ApplicationService::class)->attachDocuments($theirs, [$otherApplicants->id], $this->hrStaff);

        $interview = $this->scheduleInterview($mine, [$eve]);
        $this->scheduleInterview($theirs, [$this->maya], ['start_time' => '14:00']);

        $download = fn ($applicantId, $docId) => $this->actingAs($eve)->get(route('recruitment.applicants.documents.download', [$applicantId, $docId]));
        $download($mine->applicant_id, $attached->id)->assertOk();
        $download($mine->applicant_id, $notAttached->id)->assertForbidden();        // not submitted with the application
        $download($theirs->applicant_id, $otherApplicants->id)->assertForbidden();   // another application's file
        $download($mine->applicant_id, $otherApplicants->id)->assertNotFound();      // swapped ids: scoped binding

        $this->actingAs($eve)->get(route('recruitment.interviews.show', $interview))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('interview.application.documents', 1)->missing('interview.application.documents.0.file_path'));

        // Once the panel is cancelled the access ends.
        app(InterviewService::class)->cancel($interview, null, $this->hrStaff);
        $download($mine->applicant_id, $attached->id)->assertForbidden();
        $this->actingAs($eve)->get(route('recruitment.interviews.show', $interview))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('interview.application.documents', 0));
    }
}
