<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\JobRequisition;
use App\Models\Vacancy;
use App\Services\Recruitment\OfferApprovalService;
use App\Services\Recruitment\RecruitmentAccess;
use App\Services\Recruitment\RequisitionApprovalService;
use App\Services\Recruitment\RequisitionService;
use App\Services\Recruitment\VacancyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentDashboardController extends Controller
{
    public function __construct(
        protected RequisitionService $requisitions,
        protected RequisitionApprovalService $approvals,
        protected VacancyService $vacancies,
        protected RecruitmentAccess $access,
        protected OfferApprovalService $offerApprovals
    ) {}

    /**
     * Counts are limited to what the user may see (permissions or assignments).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $access = $this->access->for($user);

        $requisitionCounts = $access['requisitions']
            ? $this->requisitions->visibleTo($user)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        $vacancyQuery = fn () => $this->vacancies->visibleTo($user);
        $vacancyCounts = $access['vacancies'] ? $vacancyQuery()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') : collect();

        $employee = $user->employee_id ? Employee::find($user->employee_id) : null;

        return Inertia::render('app/Recruitment/Dashboard/Index', [
            'access' => $access,
            'requisitionCounts' => collect(JobRequisition::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($requisitionCounts[$s] ?? 0)]),
            'vacancyCounts' => collect(Vacancy::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($vacancyCounts[$s] ?? 0)]),
            'remainingOpenings' => $access['vacancies']
                ? (int) $vacancyQuery()->whereIn('status', [Vacancy::STATUS_OPEN, Vacancy::STATUS_ON_HOLD])->selectRaw('coalesce(sum(openings - filled_count), 0) as remaining')->value('remaining')
                : 0,
            'awaitingMyApproval' => $employee
                ? $this->approvals->awaitingDecisionBy($employee)
                    ->with(['department:id,name', 'jobTitle:id,job_title'])
                    ->orderBy('submitted_at')
                    ->limit(10)
                    ->get(['id', 'requisition_number', 'department_id', 'job_title_id', 'positions', 'submitted_at'])
                : [],
            'offersAwaitingMyApproval' => $employee
                ? $this->offerApprovals->awaitingDecisionBy($employee)
                    ->with(['applicant:id,first_name,last_name', 'vacancy:id,title'])
                    ->orderBy('submitted_at')
                    ->limit(10)
                    ->get(['id', 'offer_number', 'applicant_id', 'vacancy_id', 'position_title', 'submitted_at'])
                : [],
            'recentVacancies' => $access['vacancies']
                ? $vacancyQuery()->with(['department:id,name'])->whereIn('status', [Vacancy::STATUS_OPEN, Vacancy::STATUS_ON_HOLD])
                    ->latest('opened_at')->limit(8)->get(['id', 'vacancy_number', 'title', 'department_id', 'openings', 'filled_count', 'status', 'closing_date'])
                : [],
        ]);
    }
}
