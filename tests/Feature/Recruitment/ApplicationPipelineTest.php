<?php

namespace Tests\Feature\Recruitment;

use App\Models\ApplicantDocument;
use App\Models\ApplicantDocumentType;
use App\Models\Application;
use App\Models\RecruitmentStage;
use App\Models\Vacancy;
use App\Services\Recruitment\ApplicationPipelineService;
use App\Services\Recruitment\VacancyService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Applications, the vacancy pipeline snapshot, stage moves, screening,
 * shortlisting, rejection and withdrawal.
 */
class ApplicationPipelineTest extends RecruitmentTestCase
{
    // ----------------------------------------------------------- pipeline snapshot

    #[Test]
    public function opening_a_vacancy_copies_the_stage_template_and_later_edits_do_not_change_it(): void
    {
        $vacancy = $this->openVacancy();
        $this->assertSame([['applied', 'Applied'], ['screening', 'Screening'], ['shortlisted', 'Shortlisted'], ['interview', 'Interview'], ['assessment', 'Assessment'], ['evaluation', 'Evaluation']],
            $vacancy->stages->map(fn ($s) => [$s->stage_type, $s->name])->all());

        RecruitmentStage::where('code', 'screening')->update(['name' => 'Phone screen']);

        $this->assertSame('Screening', $this->stage($vacancy->fresh(), 'screening')->name, 'existing pipeline unchanged');
        $this->assertSame('Phone screen', $this->stage($this->openVacancy(), 'screening')->name, 'new vacancies use the new template');

        // Re-opening (on hold → open) keeps the original snapshot.
        app(VacancyService::class)->transition($vacancy, Vacancy::STATUS_ON_HOLD, $this->hrStaff, 'pause');
        app(VacancyService::class)->transition($vacancy, Vacancy::STATUS_OPEN, $this->hrStaff);
        $this->assertSame(6, $vacancy->stages()->count());
    }

    // ----------------------------------------------------------- applications

    #[Test]
    public function hr_creates_an_application_that_starts_in_applied(): void
    {
        $vacancy = $this->openVacancy();
        $applicant = $this->applicant();

        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.store'), ['applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id, 'remarks' => 'Referred by Maya'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $app = Application::sole();
        $this->assertSame(['APL-2026-00001', 'active', $this->stage($vacancy, 'applied')->id, $applicant->id, $vacancy->id],
            [$app->application_number, $app->status, $app->current_vacancy_stage_id, $app->applicant_id, $app->vacancy_id]);
        $this->assertSame([['applied', null, 'Applied', 'active', $this->hrStaff->id, 'Referred by Maya']],
            $app->history->map(fn ($h) => [$h->action, $h->from_stage_name, $h->to_stage_name, $h->to_status, $h->acted_by, $h->remarks])->all());
        $this->assertSame([$applicant->id], $vacancy->applications()->pluck('applicant_id')->all());
        $this->assertSame([$vacancy->id], $applicant->applications()->pluck('vacancy_id')->all());
    }

    #[Test]
    public function an_applicant_applies_once_per_vacancy_but_to_many_vacancies(): void
    {
        $applicant = $this->applicant();
        [$v1, $v2] = [$this->openVacancy(), $this->openVacancy()];

        $first = $this->applyTo($v1, $applicant);
        $this->applyTo($v2, $applicant);

        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.store'), ['applicant_id' => $applicant->id, 'vacancy_id' => $v1->id])
            ->assertSessionHasErrors('applicant_id');
        $this->assertSame(2, Application::count());

        // The database itself refuses a second row for the same applicant + vacancy.
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('applications')->insert(array_merge(collect($first->getAttributes())->except(['id', 'application_number'])->all(), ['application_number' => 'X-1']));
    }

    #[Test]
    public function applications_are_only_accepted_while_the_vacancy_is_open(): void
    {
        $service = app(VacancyService::class);
        $draft = $this->draftVacancy();
        $onHold = $this->openVacancy();
        $service->transition($onHold, Vacancy::STATUS_ON_HOLD, $this->hrStaff, 'pause');
        $closed = $this->openVacancy();
        $service->transition($closed, Vacancy::STATUS_CLOSED, $this->dina);
        $cancelled = $this->openVacancy();
        $service->transition($cancelled, Vacancy::STATUS_CANCELLED, $this->dina, 'withdrawn');
        $filled = $this->openVacancy();
        $service->transition($filled, Vacancy::STATUS_FILLED, $this->dina);

        $applicant = $this->applicant();
        foreach ([$draft, $onHold, $closed, $cancelled, $filled] as $vacancy) {
            $this->actingAs($this->hrStaff)->post(route('recruitment.applications.store'), ['applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id])
                ->assertSessionHasErrors('vacancy_id');
        }

        $applicant->update(['status' => 'archived']);
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.store'), ['applicant_id' => $applicant->id, 'vacancy_id' => $this->openVacancy()->id])
            ->assertSessionHasErrors('applicant_id');

        $this->assertSame(0, Application::count());
    }

    #[Test]
    public function only_users_with_application_permissions_can_create_or_list(): void
    {
        $vacancy = $this->openVacancy();
        $applicant = $this->applicant();

        foreach ([$this->ramon, $this->userWithRole('employee')] as $user) {
            $this->actingAs($user)->post(route('recruitment.applications.store'), ['applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id])->assertForbidden();
            $this->actingAs($user)->get(route('recruitment.applications.index'))->assertForbidden();
        }
        $this->assertSame(0, Application::count());
    }

    // ----------------------------------------------------------- moves

    #[Test]
    public function applied_to_screening_to_shortlisted_with_full_history(): void
    {
        $vacancy = $this->openVacancy();
        $app = $this->applyTo($vacancy);
        $applied = $this->stage($vacancy, 'applied');
        $screening = $this->stage($vacancy, 'screening');
        $shortlisted = $this->stage($vacancy, 'shortlisted');

        // Shortlisting straight from Applied is not a valid move.
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.shortlist', $app), ['expected_stage_id' => $applied->id])->assertSessionHasErrors('stage');

        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.advance', $app), ['expected_stage_id' => $applied->id, 'remarks' => 'CV looks good'])->assertSessionHasNoErrors();
        $this->assertSame($screening->id, $app->fresh()->current_vacancy_stage_id);

        // Advance can't be used to shortlist, and an unscreened application can't be shortlisted.
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.advance', $app), ['expected_stage_id' => $screening->id])->assertSessionHasErrors('stage');
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.shortlist', $app), ['expected_stage_id' => $screening->id])->assertSessionHasErrors('stage');

        $this->actingAs($this->hrStaff)->put(route('recruitment.applications.screening', $app), ['result' => 'passed', 'screened_on' => '2026-10-04', 'remarks' => 'Strong'])->assertSessionHasNoErrors();
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.shortlist', $app), ['expected_stage_id' => $screening->id, 'remarks' => 'Top 5'])->assertSessionHasNoErrors();

        $app->refresh();
        $this->assertSame([$shortlisted->id, 'shortlisted', $this->hrStaff->id], [$app->current_vacancy_stage_id, $app->status, $app->shortlisted_by]);
        $this->assertNotNull($app->shortlisted_at);
        $this->assertSame([
            ['applied', null, 'Applied', null, 'active', null],
            ['moved', 'Applied', 'Screening', 'active', 'active', 'CV looks good'],
            ['shortlisted', 'Screening', 'Shortlisted', 'active', 'shortlisted', 'Top 5'],
        ], $app->history->map(fn ($h) => [$h->action, $h->from_stage_name, $h->to_stage_name, $h->from_status, $h->to_status, $h->remarks])->all());
        $this->assertSame([$this->hrStaff->id], $app->history->pluck('acted_by')->unique()->values()->all());

        // After the shortlist the next move is Interview (phase 3); the status stays "shortlisted".
        app(ApplicationPipelineService::class)->advance($app, $shortlisted->id, $this->hrStaff);
        $this->assertSame([$this->stage($vacancy, 'interview')->id, 'shortlisted'], [$app->fresh()->current_vacancy_stage_id, $app->fresh()->status]);
    }

    #[Test]
    public function a_stale_screen_cannot_move_an_application_twice(): void
    {
        $vacancy = $this->openVacancy();
        $app = $this->applyTo($vacancy);
        $applied = $this->stage($vacancy, 'applied')->id;

        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.advance', $app), ['expected_stage_id' => $applied])->assertSessionHasNoErrors();
        $this->actingAs($this->dina)->post(route('recruitment.applications.advance', $app), ['expected_stage_id' => $applied])->assertSessionHasErrors('stage');

        $this->assertSame(2, $app->history()->count(), 'applied + one move only');
    }

    #[Test]
    public function rejection_needs_a_configured_reason_and_is_terminal(): void
    {
        $vacancy = $this->openVacancy();
        $app = $this->inScreening($vacancy);

        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.reject', $app), [])->assertSessionHasErrors('rejection_reason_id');
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.reject', $app), ['rejection_reason_id' => $this->reason('insufficient_experience')->id, 'remarks' => 'Needs 3 years.'])
            ->assertSessionHasNoErrors();

        $app->refresh();
        $this->assertSame(['rejected', $this->reason('insufficient_experience')->id, 'Needs 3 years.', $this->hrStaff->id], [$app->status, $app->rejection_reason_id, $app->rejection_remarks, $app->rejected_by]);
        $this->assertNotNull($app->rejected_at);
        $last = $app->history->last();
        $this->assertSame(['rejected', 'Screening', 'active', 'rejected', $this->reason('insufficient_experience')->id], [$last->action, $last->to_stage_name, $last->from_status, $last->to_status, $last->rejection_reason_id]);

        // Terminal: nothing moves it again.
        $stage = $app->current_vacancy_stage_id;
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.advance', $app), ['expected_stage_id' => $stage])->assertForbidden();
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.shortlist', $app), ['expected_stage_id' => $stage])->assertForbidden();
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.reject', $app), ['rejection_reason_id' => $this->reason()->id])->assertForbidden();
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.withdraw', $app), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->hrStaff)->put(route('recruitment.applications.screening', $app), ['result' => 'passed', 'screened_on' => '2026-10-04'])->assertForbidden();

        $this->expectException(ValidationException::class);
        app(ApplicationPipelineService::class)->reject($app, $this->reason()->id, $this->hrStaff);
    }

    #[Test]
    public function an_inactive_rejection_reason_cannot_be_used(): void
    {
        $app = $this->inScreening($this->openVacancy());
        $this->reason('other')->update(['is_active' => false]);

        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.reject', $app), ['rejection_reason_id' => $this->reason('other')->id])
            ->assertSessionHasErrors('rejection_reason_id');
        $this->assertSame('active', $app->fresh()->status);
    }

    #[Test]
    public function hr_can_withdraw_an_application_with_a_reason(): void
    {
        $app = $this->applyTo($this->openVacancy());

        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.withdraw', $app), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.withdraw', $app), ['reason' => 'Accepted another offer'])->assertSessionHasNoErrors();

        $app->refresh();
        $this->assertSame(['withdrawn', 'Accepted another offer', $this->hrStaff->id], [$app->status, $app->withdrawal_reason, $app->withdrawn_by]);
        $this->assertSame('withdrawn', $app->history->last()->action);
    }

    #[Test]
    public function a_filled_or_cancelled_vacancy_freezes_progress_but_still_allows_rejection(): void
    {
        $vacancy = $this->openVacancy();
        $app = $this->applyTo($vacancy);
        app(VacancyService::class)->transition($vacancy, Vacancy::STATUS_FILLED, $this->dina);

        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.advance', $app), ['expected_stage_id' => $app->current_vacancy_stage_id])->assertSessionHasErrors('stage');
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.reject', $app), ['rejection_reason_id' => $this->reason('position_filled')->id])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $app->fresh()->status);
    }

    #[Test]
    public function stage_history_is_immutable(): void
    {
        $history = $this->applyTo($this->openVacancy())->history()->first();

        try {
            $history->update(['remarks' => 'rewritten']);
            $this->fail('history was updated');
        } catch (LogicException) {
        }
        try {
            $history->delete();
            $this->fail('history was deleted');
        } catch (LogicException) {
        }
        $this->assertNull($history->fresh()->remarks);
    }

    // ----------------------------------------------------------- screening

    #[Test]
    public function screening_is_recorded_and_can_be_updated_while_in_screening(): void
    {
        $app = $this->inScreening($this->openVacancy());

        $this->actingAs($this->hrStaff)->put(route('recruitment.applications.screening', $app), ['result' => 'passed', 'screened_on' => '2026-10-03', 'remarks' => 'Good communication'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->dina)->put(route('recruitment.applications.screening', $app), ['result' => 'passed', 'screened_on' => '2026-10-04', 'remarks' => 'Confirmed references'])
            ->assertSessionHasNoErrors();

        $s = $app->screening()->first();
        $this->assertSame(['passed', 'Confirmed references', '2026-10-04', $this->dina->id, $this->dina->id], [$s->result, $s->remarks, $s->screened_on->toDateString(), $s->screened_by, $s->updated_by]);
        $this->assertSame('active', $app->fresh()->status);
    }

    #[Test]
    public function a_failed_screening_needs_a_reason_and_rejects_the_application(): void
    {
        $app = $this->inScreening($this->openVacancy());

        $this->actingAs($this->hrStaff)->put(route('recruitment.applications.screening', $app), ['result' => 'failed', 'screened_on' => '2026-10-04'])
            ->assertSessionHasErrors('rejection_reason_id');
        $this->assertNull($app->screening()->first());

        $this->actingAs($this->hrStaff)->put(route('recruitment.applications.screening', $app), [
            'result' => 'failed', 'screened_on' => '2026-10-04', 'remarks' => 'No PHP experience', 'rejection_reason_id' => $this->reason('failed_screening')->id,
        ])->assertSessionHasNoErrors();

        $app->refresh();
        $this->assertSame(['failed', 'rejected', $this->reason('failed_screening')->id], [$app->screening->result, $app->status, $app->rejection_reason_id]);
        $this->assertSame('rejected', $app->history->last()->action);
    }

    #[Test]
    public function screening_requires_the_screening_stage_and_permission(): void
    {
        $vacancy = $this->openVacancy(['hiring_manager_id' => $this->maya->employee_id]);
        $applied = $this->applyTo($vacancy);
        $this->actingAs($this->hrStaff)->put(route('recruitment.applications.screening', $applied), ['result' => 'passed', 'screened_on' => '2026-10-04'])
            ->assertSessionHasErrors('result');

        $inScreening = $this->inScreening($vacancy);
        $this->actingAs($this->hrStaff)->put(route('recruitment.applications.screening', $inScreening), ['result' => 'passed', 'screened_on' => '2026-12-31'])
            ->assertSessionHasErrors('screened_on');

        // The hiring manager can read the screening but not record it.
        $this->actingAs($this->maya)->put(route('recruitment.applications.screening', $inScreening), ['result' => 'passed', 'screened_on' => '2026-10-04'])->assertForbidden();
        $this->actingAs($this->userWithRole('employee'))->put(route('recruitment.applications.screening', $inScreening), ['result' => 'passed', 'screened_on' => '2026-10-04'])->assertForbidden();
        $this->assertNull($inScreening->screening()->first());
    }

    // ----------------------------------------------------------- hiring manager / pipeline page

    #[Test]
    public function the_vacancy_page_shows_the_pipeline_with_counts_and_stage_lists(): void
    {
        $vacancy = $this->openVacancy(['hiring_manager_id' => $this->maya->employee_id]);
        $this->applyTo($vacancy);
        $this->applyTo($vacancy);
        $screening = $this->inScreening($vacancy, 'passed');
        app(ApplicationPipelineService::class)->shortlist($screening, $screening->current_vacancy_stage_id, $this->hrStaff);
        $rejected = $this->applyTo($vacancy);
        app(ApplicationPipelineService::class)->reject($rejected, $this->reason()->id, $this->hrStaff);

        $this->actingAs($this->hrStaff)->get(route('recruitment.vacancies.show', $vacancy))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('pipeline.stages.0.name', 'Applied')->where('pipeline.stages.0.count', 2)
                ->where('pipeline.stages.1.count', 0)
                ->where('pipeline.stages.2.name', 'Shortlisted')->where('pipeline.stages.2.count', 1)
                ->where('pipeline.rejected', 1)
                ->where('stageApplications.total', 2));

        $this->actingAs($this->hrStaff)->get(route('recruitment.vacancies.show', [$vacancy, 'stage' => $this->stage($vacancy, 'shortlisted')->id]))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('stageApplications.total', 1)->where('stageApplications.data.0.id', $screening->id));
        $this->actingAs($this->hrStaff)->get(route('recruitment.vacancies.show', [$vacancy, 'stage' => 'rejected']))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('stageApplications.data.0.id', $rejected->id));

        // The assigned hiring manager sees the pipeline and can open applications, but not act on them.
        $this->actingAs($this->maya)->get(route('recruitment.vacancies.show', $vacancy))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('pipeline.stages.0.count', 2));
        $this->actingAs($this->maya)->get(route('recruitment.applications.show', $screening))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('can.reject', false)->where('can.advance', false)->where('can.addNote', true)->where('can.viewScreening', true));
        $this->actingAs($this->maya)->post(route('recruitment.applications.reject', $screening), ['rejection_reason_id' => $this->reason()->id])->assertForbidden();
        $this->actingAs($this->maya)->post(route('recruitment.applications.notes.store', $screening), ['body' => 'Strong portfolio.'])->assertSessionHasNoErrors();

        // Applications to other vacancies stay hidden from her.
        $other = $this->applyTo($this->openVacancy());
        $this->actingAs($this->maya)->get(route('recruitment.applications.show', $other))->assertForbidden();
        $this->actingAs($this->maya)->get(route('recruitment.applications.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('applications.total', 4));
    }

    #[Test]
    public function notes_are_attributed_to_the_employee_who_wrote_them(): void
    {
        $app = $this->applyTo($this->openVacancy());

        $this->actingAs($this->dina)->post(route('recruitment.applications.notes.store', $app), ['body' => 'Call after 3pm.'])->assertSessionHasNoErrors();
        $this->actingAs($this->outsider)->post(route('recruitment.applications.notes.store', $app), ['body' => 'x'])->assertForbidden();

        $note = $app->notes()->sole();
        $this->assertSame(['Call after 3pm.', $this->dina->id, $this->dina->employee_id, 'Dina Director'], [$note->body, $note->author_user_id, $note->author_employee_id, $note->author_name]);
    }

    #[Test]
    public function documents_can_be_attached_only_from_the_applicants_own_files(): void
    {
        $applicant = $this->applicant();
        $other = $this->applicant();
        $type = ApplicantDocumentType::where('code', 'resume')->firstOrFail();
        $mine = $applicant->documents()->create(['applicant_document_type_id' => $type->id, 'original_name' => 'cv.pdf', 'file_path' => 'recruitment/a/1.pdf', 'file_size' => 10, 'uploaded_at' => now()]);
        $theirs = $other->documents()->create(['applicant_document_type_id' => $type->id, 'original_name' => 'cv.pdf', 'file_path' => 'recruitment/a/2.pdf', 'file_size' => 10, 'uploaded_at' => now()]);
        $app = $this->applyTo($this->openVacancy(), $applicant);

        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.documents.attach', $app), ['document_ids' => [$theirs->id]])->assertSessionHasErrors('document_ids.0');
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.documents.attach', $app), ['document_ids' => [$mine->id]])->assertSessionHasNoErrors();
        $this->actingAs($this->hrStaff)->post(route('recruitment.applications.documents.attach', $app), ['document_ids' => [$mine->id]])->assertSessionHasNoErrors();

        // Linked once, never copied.
        $this->assertSame([$mine->id], $app->documents()->pluck('applicant_documents.id')->all());
        $this->assertSame(2, ApplicantDocument::count());
    }
}
