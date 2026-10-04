<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\OfferActionRequest;
use App\Http\Requests\Recruitment\OfferRequest;
use App\Http\Requests\Recruitment\RejectOfferRequest;
use App\Http\Requests\Recruitment\RespondOfferRequest;
use App\Http\Requests\Recruitment\WithdrawOfferRequest;
use App\Models\Application;
use App\Models\ApplicationConversion;
use App\Models\JobOffer;
use App\Services\Recruitment\OfferApprovalService;
use App\Services\Recruitment\OfferService;
use App\Services\Recruitment\RecruitmentFormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Job offers: list, detail, draft, approval decisions, issue, candidate
 * response (HR-side) and withdrawal. No public routes: there is no applicant
 * portal in this phase.
 */
class OfferController extends Controller
{
    public function __construct(
        protected OfferService $offers,
        protected OfferApprovalService $approvals,
        protected RecruitmentFormOptions $options
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'awaiting']);

        return Inertia::render('app/Recruitment/Offers/Index', [
            'offers' => $this->offers->getPaginatedOffers($request->user(), $filters),
            'filters' => $filters,
            'statuses' => JobOffer::STATUSES,
        ]);
    }

    public function store(OfferRequest $request, Application $application): RedirectResponse
    {
        $offer = $this->offers->createDraft($application, $request->validated(), $request->user());

        return redirect()->route('recruitment.offers.show', $offer)->with('success', 'Offer draft created.');
    }

    public function show(Request $request, JobOffer $offer): Response
    {
        $user = $request->user();
        $this->offers->expireIfDue($offer);
        $offer->refresh()->load([
            'applicant:id,applicant_number,first_name,middle_name,last_name,email,phone',
            'vacancy:id,vacancy_number,title,openings,filled_count,hiring_manager_id',
            'application:id,application_number,status',
            'selection:id,decision,decided_at,decided_by,remarks', 'selection.decider:id,username',
            'approvals.actor:id,username',
            'events.actor:id,username',
        ]);
        $canUpdate = $user->can('update', $offer);

        return Inertia::render('app/Recruitment/Offers/Show', [
            'offer' => $offer,
            'options' => $canUpdate ? $this->options->offerTerms() : null,
            'conversion' => $user->can('viewConversion', $offer->application)
                ? ApplicationConversion::where('job_offer_id', $offer->id)->first(['id', 'employee_number', 'conversion_type', 'converted_at'])
                : null,
            'can' => [
                'update' => $canUpdate,
                'submit' => $canUpdate,
                'approve' => $user->can('approve', $offer),
                'reject' => $user->can('reject', $offer),
                'issue' => $user->can('issue', $offer),
                'respond' => $user->can('respond', $offer),
                'withdraw' => $user->can('withdraw', $offer),
                'viewApplication' => $user->can('view', $offer->application),
            ],
        ]);
    }

    public function update(OfferRequest $request, JobOffer $offer): RedirectResponse
    {
        $this->offers->updateDraft($offer, $request->validated(), $request->user());

        return back()->with('success', 'Offer draft saved.');
    }

    public function submit(OfferActionRequest $request, JobOffer $offer): RedirectResponse
    {
        $this->offers->submit($offer, $request->validated('remarks'), $request->user());

        return back()->with('success', 'Offer submitted for approval.');
    }

    public function approve(OfferActionRequest $request, JobOffer $offer): RedirectResponse
    {
        $this->approvals->approve($offer, $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Offer approved.');
    }

    public function reject(RejectOfferRequest $request, JobOffer $offer): RedirectResponse
    {
        $this->approvals->reject($offer, $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Offer rejected.');
    }

    public function issue(OfferActionRequest $request, JobOffer $offer): RedirectResponse
    {
        $this->offers->issue($offer, $request->validated('remarks'), $request->user());

        return back()->with('success', 'Offer issued.');
    }

    public function respond(RespondOfferRequest $request, JobOffer $offer): RedirectResponse
    {
        $this->offers->respond($offer, $request->validated('response'), $request->validated('remarks'), $request->user());

        return back()->with('success', 'Candidate response recorded.');
    }

    public function withdraw(WithdrawOfferRequest $request, JobOffer $offer): RedirectResponse
    {
        $this->offers->withdraw($offer, $request->validated('reason'), $request->user());

        return back()->with('success', 'Offer withdrawn.');
    }
}
