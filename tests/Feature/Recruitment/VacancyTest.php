<?php

namespace Tests\Feature\Recruitment;

use App\Models\JobTitle;
use App\Models\Vacancy;
use App\Services\Recruitment\VacancyService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;

class VacancyTest extends RecruitmentTestCase
{
    // ----------------------------------------------------------- create

    #[Test]
    public function hr_creates_a_standalone_draft_vacancy(): void
    {
        $this->actingAs($this->hrStaff)
            ->post(route('recruitment.vacancies.store'), $this->vacancyPayload(['salary_min' => 50000, 'salary_max' => 70000, 'salary_currency' => 'php']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $v = Vacancy::sole();
        $this->assertSame(['VAC-2026-00001', 'draft', 'Software Developer', 2, 0, 2, null, 'PHP', $this->hrStaff->id],
            [$v->vacancy_number, $v->status, $v->title, $v->openings, $v->filled_count, $v->remaining_openings, $v->job_requisition_id, $v->salary_currency, $v->created_by]);
    }

    #[Test]
    public function vacancy_numbers_are_unique_and_sequential(): void
    {
        $this->assertSame(['VAC-2026-00001', 'VAC-2026-00002', 'VAC-2026-00003'], [
            $this->draftVacancy()->vacancy_number, $this->draftVacancy()->vacancy_number, $this->draftVacancy()->vacancy_number,
        ]);
    }

    #[Test]
    public function vacancies_are_validated(): void
    {
        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.store'), ['openings' => 0, 'visibility' => 'secret'])
            ->assertSessionHasErrors(['job_title_id', 'department_id', 'openings', 'visibility']);

        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.store'), $this->vacancyPayload([
            'salary_min' => 80000, 'salary_max' => 50000, 'opening_date' => '2026-11-01', 'closing_date' => '2026-10-01',
        ]))->assertSessionHasErrors(['salary_max', 'closing_date']);

        $this->assertSame(0, Vacancy::count());
    }

    #[Test]
    public function a_vacancy_from_an_approved_requisition_copies_its_position_data(): void
    {
        $req = $this->approvedRequisition(['positions' => 3]);
        $other = JobTitle::create(['job_title' => 'Accountant']);

        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.store'), $this->vacancyPayload([
            'job_requisition_id' => $req->id, 'job_title_id' => $other->id, 'openings' => 2,
        ]))->assertSessionHasNoErrors();

        $v = Vacancy::sole();
        $this->assertSame([$req->id, $this->developer->id, $this->it->id, $this->mainOffice->id, $this->regular->id, 2],
            [$v->job_requisition_id, $v->job_title_id, $v->department_id, $v->location_id, $v->employment_status_id, $v->openings]);
    }

    #[Test]
    public function a_vacancy_keeps_its_own_copy_when_the_requisition_changes(): void
    {
        $req = $this->approvedRequisition();
        $vacancy = app(VacancyService::class)->createVacancy($this->vacancyPayload(['job_requisition_id' => $req->id, 'openings' => 1]), $this->hrStaff);

        DB::table('job_requisitions')->where('id', $req->id)->update(['job_title_id' => JobTitle::create(['job_title' => 'Other'])->id, 'location_id' => null]);

        $this->assertSame([$this->developer->id, $this->mainOffice->id], [$vacancy->fresh()->job_title_id, $vacancy->fresh()->location_id]);
    }

    #[Test]
    public function vacancies_cannot_come_from_an_unapproved_requisition(): void
    {
        $draft = $this->draftRequisition();
        $pending = $this->submittedRequisition();
        $rejected = $this->submittedRequisition();
        $this->actingAs($this->maya)->post(route('recruitment.requisitions.reject', $rejected), ['remarks' => 'No']);

        foreach ([$draft, $pending, $rejected] as $req) {
            $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.store'), $this->vacancyPayload(['job_requisition_id' => $req->id, 'openings' => 1]))
                ->assertSessionHasErrors('job_requisition_id');
        }
        $this->assertSame(0, Vacancy::count());
    }

    #[Test]
    public function openings_cannot_exceed_the_requisitions_approved_positions(): void
    {
        $req = $this->approvedRequisition(['positions' => 3]);
        $service = app(VacancyService::class);

        $first = $service->createVacancy($this->vacancyPayload(['job_requisition_id' => $req->id, 'openings' => 2]), $this->hrStaff);

        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.store'), $this->vacancyPayload(['job_requisition_id' => $req->id, 'openings' => 2]))
            ->assertSessionHasErrors('openings');

        $this->actingAs($this->hrStaff)->put(route('recruitment.vacancies.update', $first), $this->vacancyPayload(['openings' => 4]))
            ->assertSessionHasErrors('openings');

        // Cancelling a vacancy frees its openings.
        $service->transition($first, Vacancy::STATUS_CANCELLED, $this->hrStaff);
        $this->assertSame(3, $service->createVacancy($this->vacancyPayload(['job_requisition_id' => $req->id, 'openings' => 3]), $this->hrStaff)->openings);
    }

    #[Test]
    public function openings_cannot_drop_below_the_number_already_filled(): void
    {
        $vacancy = $this->openVacancy(['openings' => 3]);
        DB::table('vacancies')->where('id', $vacancy->id)->update(['filled_count' => 2]); // set by hiring in a later phase

        $this->actingAs($this->hrStaff)->put(route('recruitment.vacancies.update', $vacancy), $this->vacancyPayload(['openings' => 1]))
            ->assertSessionHasErrors('openings');
        $this->assertSame([3, 1], [$vacancy->fresh()->openings, $vacancy->fresh()->remaining_openings]);
    }

    // ----------------------------------------------------------- transitions

    #[Test]
    public function the_allowed_lifecycle_open_hold_reopen_close(): void
    {
        $v = $this->draftVacancy();

        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.open', $v))->assertSessionHasNoErrors();
        $v->refresh();
        $this->assertSame(['open', '2026-10-04'], [$v->status, $v->opening_date->toDateString()]);
        $this->assertNotNull($v->opened_at);

        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.hold', $v))->assertSessionHasErrors('reason');
        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.hold', $v), ['reason' => 'Budget review'])->assertSessionHasNoErrors();
        $this->assertSame(['on_hold', 'Budget review'], [$v->fresh()->status, $v->fresh()->status_reason]);

        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.reopen', $v))->assertSessionHasNoErrors();
        $this->assertSame('open', $v->fresh()->status);

        $this->actingAs($this->dina)->post(route('recruitment.vacancies.close', $v), ['reason' => 'Enough candidates'])->assertSessionHasNoErrors();
        $this->assertSame('closed', $v->fresh()->status);
        $this->assertNotNull($v->fresh()->closed_at);
    }

    #[Test]
    public function an_open_vacancy_can_be_filled_or_cancelled(): void
    {
        $filled = $this->openVacancy();
        $cancelled = $this->openVacancy();

        $this->actingAs($this->dina)->post(route('recruitment.vacancies.fill', $filled))->assertSessionHasNoErrors();
        $this->actingAs($this->dina)->post(route('recruitment.vacancies.cancel', $cancelled))->assertSessionHasErrors('reason');
        $this->actingAs($this->dina)->post(route('recruitment.vacancies.cancel', $cancelled), ['reason' => 'Role withdrawn'])->assertSessionHasNoErrors();

        $this->assertSame(['filled', 'cancelled'], [$filled->fresh()->status, $cancelled->fresh()->status]);
    }

    #[Test]
    public function invalid_transitions_are_rejected(): void
    {
        $draft = $this->draftVacancy();
        $this->actingAs($this->dina)->post(route('recruitment.vacancies.hold', $draft), ['reason' => 'x'])->assertSessionHasErrors('status');
        $this->actingAs($this->dina)->post(route('recruitment.vacancies.fill', $draft))->assertSessionHasErrors('status');

        $closed = $this->openVacancy();
        app(VacancyService::class)->transition($closed, Vacancy::STATUS_CLOSED, $this->dina);
        foreach (['open' => [], 'reopen' => [], 'hold' => ['reason' => 'x'], 'fill' => [], 'cancel' => ['reason' => 'x']] as $action => $data) {
            $this->actingAs($this->dina)->post(route("recruitment.vacancies.{$action}", $closed), $data)->assertSessionHasErrors('status');
        }
        $this->assertSame('closed', $closed->fresh()->status);

        // Terminal vacancies can't be edited either.
        $this->actingAs($this->dina)->put(route('recruitment.vacancies.update', $closed), $this->vacancyPayload())->assertForbidden();
    }

    #[Test]
    public function status_changes_need_the_matching_permission(): void
    {
        $v = $this->openVacancy();

        // hr_staff may publish (hold/reopen) but not close/fill/cancel.
        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.close', $v))->assertForbidden();
        $this->actingAs($this->hrStaff)->post(route('recruitment.vacancies.cancel', $v), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->outsider)->post(route('recruitment.vacancies.hold', $v), ['reason' => 'x'])->assertForbidden();
        $this->assertSame('open', $v->fresh()->status);
    }

    // ----------------------------------------------------------- hiring manager

    #[Test]
    public function an_assigned_hiring_manager_sees_only_their_vacancies_and_edits_content_only(): void
    {
        $mine = $this->openVacancy(['hiring_manager_id' => $this->maya->employee_id, 'title' => 'Backend Developer']);
        $notMine = $this->openVacancy(['hiring_manager_id' => $this->outsider->employee_id]);

        $this->actingAs($this->maya)->get(route('recruitment.vacancies.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('vacancies.total', 1)->where('vacancies.data.0.id', $mine->id));
        $this->actingAs($this->maya)->get(route('recruitment.vacancies.show', $mine))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('can.update', true)->where('can.publish', false)->where('can.close', false));
        $this->actingAs($this->maya)->get(route('recruitment.vacancies.show', $notMine))->assertForbidden();

        $this->actingAs($this->maya)->put(route('recruitment.vacancies.update', $mine), [
            'description' => 'Updated by the hiring manager.', 'title' => 'Hijacked', 'openings' => 9,
        ])->assertSessionHasNoErrors();
        $mine->refresh();
        $this->assertSame(['Updated by the hiring manager.', 'Backend Developer', 2], [$mine->description, $mine->title, $mine->openings]);

        $this->actingAs($this->maya)->post(route('recruitment.vacancies.hold', $mine), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->maya)->post(route('recruitment.vacancies.close', $mine))->assertForbidden();
    }

    #[Test]
    public function supervisors_get_no_vacancy_access_without_an_assignment(): void
    {
        $vacancy = $this->openVacancy();

        $this->actingAs($this->maya)->get(route('recruitment.vacancies.index'))->assertForbidden();
        $this->actingAs($this->maya)->get(route('recruitment.vacancies.show', $vacancy))->assertForbidden();
        $this->actingAs($this->maya)->post(route('recruitment.vacancies.store'), $this->vacancyPayload())->assertForbidden();
    }

    #[Test]
    public function the_detail_page_shows_the_position_and_its_origin(): void
    {
        $req = $this->approvedRequisition();
        $v = app(VacancyService::class)->createVacancy($this->vacancyPayload(['job_requisition_id' => $req->id, 'openings' => 2, 'hiring_manager_id' => $this->maya->employee_id]), $this->hrStaff);

        $this->actingAs($this->hrStaff)->get(route('recruitment.vacancies.show', $v))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('app/Recruitment/Vacancies/Show', false)
                ->where('vacancy.job_title.job_title', 'Software Developer')
                ->where('vacancy.department.name', 'Information Technology')
                ->where('vacancy.hiring_manager.emp_first_name', 'Maya')
                ->where('vacancy.requisition.requisition_number', $req->requisition_number)
                ->where('vacancy.remaining_openings', 2)
                ->where('transitions', ['open', 'cancelled']));
    }
}
