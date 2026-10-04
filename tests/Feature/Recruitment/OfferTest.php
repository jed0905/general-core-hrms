<?php

namespace Tests\Feature\Recruitment;

use App\Models\Employee;
use App\Models\JobOffer;
use App\Models\JobOfferEvent;
use App\Models\User;
use App\Services\Recruitment\OfferApprovalService;
use App\Services\Recruitment\OfferService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Offer lifecycle: draft → pending approval → approved → issued → accepted /
 * declined / expired, withdrawal, immutability, one active offer, access.
 */
class OfferTest extends RecruitmentTestCase
{
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->hrApprovalChain();
    }

    private function selectedApp(array $vacancy = [])
    {
        $app = $this->evaluated($this->openVacancy($vacancy));
        $this->select($app);

        return $app->fresh();
    }

    private function create(User $as, $application, array $overrides = [])
    {
        return $this->actingAs($as)->post(route('recruitment.applications.offers.store', $application), $this->offerTerms($overrides));
    }

    #[Test]
    public function hr_creates_and_edits_a_numbered_draft_with_snapshotted_terms(): void
    {
        $app = $this->selectedApp();

        $this->create($this->hrStaff, $app)->assertSessionHasNoErrors()->assertRedirect();
        $offer = JobOffer::sole();
        $this->assertSame(
            ['OFF-2026-00001', 'draft', $app->id, $app->vacancy_id, $app->applicant_id, 'Software Developer', 'Information Technology', 'Regular', 'Main Office, Pasig City', '55000.00', 'monthly', 'PHP', '2026-11-02', '2026-10-20', $this->hrStaff->id],
            [$offer->offer_number, $offer->status, $offer->application_id, $offer->vacancy_id, $offer->applicant_id, $offer->position_title, $offer->department_name, $offer->employment_type, $offer->work_location,
                $offer->base_salary, $offer->salary_frequency, $offer->currency, $offer->proposed_start_date->toDateString(), $offer->expiry_date->toDateString(), $offer->created_by]
        );

        $this->actingAs($this->hrStaff)->put(route('recruitment.offers.update', $offer), $this->offerTerms(['base_salary' => 60000, 'currency' => 'usd', 'work_location' => 'Remote']))->assertSessionHasNoErrors();
        $offer->refresh();
        $this->assertSame(['60000.00', 'USD', 'Remote', $this->hrStaff->id], [$offer->base_salary, $offer->currency, $offer->work_location, $offer->updated_by]);
        $this->assertSame(['created', 'updated'], $offer->events->pluck('event')->all());

        // The next offer (another application) gets the next number.
        $this->draftOffer($this->selectedApp());
        $this->assertSame('OFF-2026-00002', JobOffer::latest('id')->value('offer_number'));
    }

    #[Test]
    public function offer_terms_are_validated(): void
    {
        $app = $this->selectedApp();

        $this->create($this->hrStaff, $app, ['expiry_date' => '2026-10-01'])->assertSessionHasErrors('expiry_date');
        $this->create($this->hrStaff, $app, ['proposed_start_date' => '2026-10-10'])->assertSessionHasErrors('proposed_start_date');
        $this->create($this->hrStaff, $app, ['base_salary' => -1])->assertSessionHasErrors('base_salary');
        $this->create($this->hrStaff, $app, ['base_salary' => 'lots'])->assertSessionHasErrors('base_salary');
        $this->create($this->hrStaff, $app, ['salary_frequency' => 'fortnightly'])->assertSessionHasErrors('salary_frequency');
        $this->create($this->hrStaff, $app, ['currency' => 'PESO'])->assertSessionHasErrors('currency');
        $this->create($this->hrStaff, $app, ['job_title_id' => 99999])->assertSessionHasErrors('job_title_id');
        $this->create($this->hrStaff, $app, ['location_id' => 99999])->assertSessionHasErrors('location_id');
        $this->assertSame(0, JobOffer::count());
    }

    #[Test]
    public function only_a_selected_candidate_gets_an_offer_and_only_one_active_offer(): void
    {
        $vacancy = $this->openVacancy(['openings' => 3]);
        $notSelected = $this->evaluated($vacancy);
        $this->create($this->hrStaff, $notSelected)->assertSessionHasErrors(['offer' => 'Only a selected candidate can receive an offer.']);

        $app = $this->evaluated($vacancy);
        $this->select($app);
        $first = $this->draftOffer($app);
        $this->create($this->hrStaff, $app)->assertSessionHasErrors(['offer' => "This application already has offer {$first->offer_number} (draft). Withdraw it before preparing another."]);

        // After withdrawal a new offer may be prepared; the old one stays on record.
        app(OfferService::class)->withdraw($first, 'Revising salary', $this->hrStaff);
        $this->create($this->hrStaff, $app, ['base_salary' => 58000])->assertSessionHasNoErrors();
        $this->assertSame(['withdrawn', 'draft'], JobOffer::where('application_id', $app->id)->orderBy('id')->pluck('status')->all());

        // The database also refuses a second active offer.
        $this->expectException(UniqueConstraintViolationException::class);
        JobOffer::create(array_merge(JobOffer::where('application_id', $app->id)->latest('id')->first()->only([
            'application_id', 'vacancy_id', 'applicant_id', 'application_selection_id', 'position_title', 'proposed_start_date', 'expiry_date', 'base_salary', 'salary_frequency', 'currency',
        ]), ['offer_number' => 'MANUAL-1', 'status' => 'draft']));
    }

    #[Test]
    public function the_full_happy_path_ends_at_acceptance_without_creating_an_employee(): void
    {
        $app = $this->selectedApp();
        $employees = Employee::count();
        $users = User::count();
        $offer = $this->draftOffer($app);

        $this->actingAs($this->hrStaff)->post(route('recruitment.offers.submit', $offer), ['remarks' => 'Please review'])->assertSessionHasNoErrors();
        $this->assertSame('pending_approval', $offer->fresh()->status);
        $this->actingAs($this->hrStaff)->post(route('recruitment.offers.issue', $offer))->assertForbidden(); // not approved yet

        $this->actingAs($this->manager)->post(route('recruitment.offers.approve', $offer), ['remarks' => 'OK'])->assertSessionHasNoErrors();
        $this->assertSame('pending_approval', $offer->fresh()->status);
        $this->actingAs($this->dina)->post(route('recruitment.offers.approve', $offer))->assertSessionHasNoErrors();
        $this->assertSame('approved', $offer->fresh()->status);

        $this->actingAs($this->hrStaff)->post(route('recruitment.offers.issue', $offer))->assertSessionHasNoErrors();
        $offer->refresh();
        $this->assertSame(['issued', '2026-10-05', $this->hrStaff->id], [$offer->status, $offer->offer_date->toDateString(), $offer->issued_by]);

        Carbon::setTestNow('2026-10-20 17:00:00'); // the expiry date itself is still in time
        $this->actingAs($this->hrStaff)->post(route('recruitment.offers.respond', $offer), ['response' => 'accepted', 'remarks' => 'Signed copy received'])->assertSessionHasNoErrors();
        $offer->refresh();
        $this->assertSame(['accepted', 'accepted', $this->hrStaff->id, 'Signed copy received', '2026-10-20 17:00:00'],
            [$offer->status, $offer->response, $offer->response_recorded_by, $offer->response_remarks, $offer->responded_at->format('Y-m-d H:i:s')]);

        $this->assertSame(['created', 'submitted', 'step_approved', 'approved', 'issued', 'candidate_accepted'], $offer->events->pluck('event')->all());
        $this->assertSame([$employees, $users], [Employee::count(), User::count()], 'Phase 4 never creates employees or accounts');
        $this->assertSame('shortlisted', $app->fresh()->status);
    }

    #[Test]
    public function the_candidate_can_decline_and_an_approver_rejection_is_recorded_separately(): void
    {
        $declined = $this->approvedOffer($this->selectedApp(), $this->manager);
        app(OfferService::class)->issue($declined, null, $this->hrStaff);
        $this->actingAs($this->hrStaff)->post(route('recruitment.offers.respond', $declined), ['response' => 'declined', 'remarks' => 'Counter-offer from current employer'])->assertSessionHasNoErrors();
        $this->assertSame(['declined', 'declined'], [$declined->fresh()->status, $declined->fresh()->response]);
        $this->assertSame('candidate_declined', $declined->events->last()->event);

        $rejected = app(OfferService::class)->submit($this->draftOffer($this->selectedApp()), null, $this->hrStaff);
        $this->actingAs($this->manager)->post(route('recruitment.offers.reject', $rejected), [])->assertSessionHasErrors('remarks');
        $this->actingAs($this->manager)->post(route('recruitment.offers.reject', $rejected), ['remarks' => 'Above budget'])->assertSessionHasNoErrors();
        $rejected->refresh();
        $this->assertSame(['rejected', null, null], [$rejected->status, $rejected->response, $rejected->responded_at]);
        $this->assertSame(['approval_rejected', $this->manager->id, 'Above budget'], [$rejected->events->last()->event, $rejected->events->last()->actor_id, $rejected->events->last()->remarks]);
        $this->assertSame(['rejected', 'skipped'], $rejected->approvals->pluck('status')->all());
    }

    #[Test]
    public function an_offer_expires_after_its_expiry_date_and_cannot_be_accepted(): void
    {
        $offer = $this->approvedOffer($this->selectedApp(), $this->manager);
        app(OfferService::class)->issue($offer, null, $this->hrStaff);

        Carbon::setTestNow('2026-10-21 08:00:00');
        $this->actingAs($this->hrStaff)->post(route('recruitment.offers.respond', $offer), ['response' => 'accepted'])->assertSessionHasErrors('status');
        $offer->refresh();
        $this->assertSame(['expired', null, '2026-10-21 08:00:00'], [$offer->status, $offer->response, $offer->expired_at->format('Y-m-d H:i:s')]);
        $this->assertSame([JobOfferEvent::EXPIRED, null], [$offer->events->last()->event, $offer->events->last()->actor_id]);

        // The daily command expires the rest and is safe to re-run.
        $other = $this->approvedOffer($this->selectedApp(), $this->manager);
        Carbon::setTestNow('2026-10-21 08:00:00');
        $other->refresh();
        $this->assertSame('approved', $other->status);
        $this->actingAs($this->hrStaff)->post(route('recruitment.offers.issue', $other))->assertSessionHasErrors('status'); // past expiry: can't issue
        Carbon::setTestNow('2026-10-05 12:00:00');
        app(OfferService::class)->issue($other, null, $this->hrStaff);
        Carbon::setTestNow('2026-10-22 00:10:00');
        Artisan::call('recruitment:expire-offers');
        Artisan::call('recruitment:expire-offers');
        $this->assertSame('expired', $other->fresh()->status);
        $this->assertSame(1, $other->events()->where('event', 'expired')->count());
    }

    #[Test]
    public function withdrawal_is_recorded_and_final(): void
    {
        foreach (['draft', 'pending_approval', 'approved', 'issued'] as $state) {
            $app = $this->selectedApp();
            $offer = $this->draftOffer($app);
            if ($state !== 'draft') {
                app(OfferService::class)->submit($offer, null, $this->hrStaff);
            }
            if (in_array($state, ['approved', 'issued'], true)) {
                app(OfferApprovalService::class)->approve($offer, $this->manager);
                app(OfferApprovalService::class)->approve($offer, $this->dina);
            }
            if ($state === 'issued') {
                app(OfferService::class)->issue($offer, null, $this->hrStaff);
            }

            $this->actingAs($this->hrStaff)->post(route('recruitment.offers.withdraw', $offer), [])->assertSessionHasErrors('reason');
            $this->actingAs($this->hrStaff)->post(route('recruitment.offers.withdraw', $offer), ['reason' => 'Budget freeze'])->assertSessionHasNoErrors();
            $offer->refresh();
            $this->assertSame(['withdrawn', $this->hrStaff->id, 'Budget freeze'], [$offer->status, $offer->withdrawn_by, $offer->withdrawal_reason], $state);
            $this->assertSame(0, $offer->approvals()->where('status', 'pending')->count());

            foreach (['issue', 'withdraw', 'submit', 'approve'] as $action) {
                $this->actingAs($this->dina)->post(route("recruitment.offers.{$action}", $offer), ['reason' => 'x'])->assertForbidden();
            }
            $this->actingAs($this->hrStaff)->post(route('recruitment.offers.respond', $offer), ['response' => 'accepted'])->assertForbidden();
        }
    }

    #[Test]
    public function invalid_transitions_are_refused_by_the_service_too(): void
    {
        $offer = $this->draftOffer($this->selectedApp());
        $service = app(OfferService::class);
        $refused = function (callable $action) {
            try {
                $action();
                $this->fail('an invalid offer transition succeeded');
            } catch (ValidationException) {
            }
        };

        $refused(fn () => $service->issue($offer, null, $this->hrStaff));             // draft → issued
        $refused(fn () => $service->respond($offer, 'accepted', null, $this->hrStaff)); // draft → accepted

        $offer = $this->approvedOffer($this->selectedApp(), $this->manager);
        $service->issue($offer, null, $this->hrStaff);
        $service->respond($offer, 'accepted', null, $this->hrStaff);

        $refused(fn () => $service->issue($offer, null, $this->hrStaff));               // accepted → issued
        $refused(fn () => $service->updateDraft($offer, $this->offerTerms(), $this->hrStaff)); // accepted → draft edit
        $refused(fn () => $service->withdraw($offer, 'x', $this->hrStaff));            // accepted → withdrawn
        $refused(fn () => $service->respond($offer, 'declined', null, $this->hrStaff)); // second response
        $this->assertSame('accepted', $offer->fresh()->status);
    }

    #[Test]
    public function terms_freeze_on_submission_and_final_offers_are_fully_immutable(): void
    {
        $offer = app(OfferService::class)->submit($this->draftOffer($this->selectedApp()), null, $this->hrStaff);
        $this->actingAs($this->hrStaff)->put(route('recruitment.offers.update', $offer), $this->offerTerms(['base_salary' => 99999]))->assertForbidden();

        try {
            $offer->fresh()->update(['base_salary' => 99999]);
            $this->fail('pending offer terms changed');
        } catch (LogicException) {
        }

        app(OfferApprovalService::class)->approve($offer, $this->manager);
        app(OfferApprovalService::class)->approve($offer, $this->dina);
        $offer = app(OfferService::class)->issue($offer->fresh(), null, $this->hrStaff);
        foreach (['base_salary' => 1, 'proposed_start_date' => '2027-01-01', 'expiry_date' => '2027-01-01', 'position_title' => 'CTO', 'employment_type' => 'Probationary', 'work_location' => 'Moon', 'benefits' => 'all'] as $column => $value) {
            try {
                $offer->fresh()->update([$column => $value]);
                $this->fail("issued offer {$column} changed");
            } catch (LogicException) {
            }
        }

        $accepted = app(OfferService::class)->respond($offer, 'accepted', null, $this->hrStaff);
        foreach ([fn () => $accepted->fresh()->update(['status' => 'draft']), fn () => $accepted->fresh()->update(['response_remarks' => 'edited']), fn () => $accepted->fresh()->delete()] as $write) {
            try {
                $write();
                $this->fail('accepted offer changed');
            } catch (LogicException) {
            }
        }
        $this->assertSame(['accepted', '55000.00'], [$accepted->fresh()->status, $accepted->fresh()->base_salary]);
    }

    #[Test]
    public function access_hr_hiring_manager_approver_and_others(): void
    {
        $app = $this->selectedApp(['hiring_manager_id' => $this->ramon->employee_id]);
        $offer = app(OfferService::class)->submit($this->draftOffer($app), null, $this->hrStaff);
        $other = $this->draftOffer($this->selectedApp());
        $employee = $this->userWithRole('employee');
        $payroll = $this->userWithRole('payroll');

        $this->actingAs($this->hrStaff)->get(route('recruitment.offers.show', $offer))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('app/Recruitment/Offers/Show', false)->where('can.approve', false)->where('can.withdraw', true)->has('offer.approvals', 2));
        $this->actingAs($this->manager)->get(route('recruitment.offers.show', $offer))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('can.approve', true));
        $this->actingAs($this->dina)->get(route('recruitment.offers.show', $offer))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('can.approve', false)); // not her turn yet

        // Hiring manager: views their own vacancy's offers, nothing else, no actions.
        $this->actingAs($this->ramon)->get(route('recruitment.offers.show', $offer))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('can.withdraw', false)->where('can.approve', false));
        $this->actingAs($this->ramon)->get(route('recruitment.offers.show', $other))->assertForbidden();
        $this->actingAs($this->ramon)->get(route('recruitment.offers.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('offers.data', 1)->where('offers.data.0.id', $offer->id));
        $this->actingAs($this->ramon)->post(route('recruitment.offers.withdraw', $offer), ['reason' => 'x'])->assertForbidden();
        $this->create($this->ramon, $app)->assertForbidden();

        foreach ([$this->outsider, $this->maya, $employee, $payroll] as $user) {
            $this->actingAs($user)->get(route('recruitment.offers.show', $offer))->assertForbidden();
            $this->actingAs($user)->post(route('recruitment.offers.approve', $offer))->assertForbidden();
            $this->actingAs($user)->post(route('recruitment.offers.withdraw', $offer), ['reason' => 'x'])->assertForbidden();
        }
        foreach ([$employee, $payroll, $this->outsider] as $user) {
            $this->actingAs($user)->get(route('recruitment.offers.index'))->assertForbidden();
        }

        // HR staff can't approve; HR staff can't decide selection either.
        $this->actingAs($this->hrStaff)->post(route('recruitment.offers.approve', $offer))->assertForbidden();
        $this->assertSame('pending_approval', $offer->fresh()->status);
    }
}
