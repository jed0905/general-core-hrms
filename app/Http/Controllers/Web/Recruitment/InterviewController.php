<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\CloseInterviewRequest;
use App\Http\Requests\Recruitment\RescheduleInterviewRequest;
use App\Http\Requests\Recruitment\SaveEvaluationRequest;
use App\Http\Requests\Recruitment\ScheduleInterviewRequest;
use App\Http\Requests\Recruitment\UpdateInterviewRequest;
use App\Models\Application;
use App\Models\ApplicationEvaluation;
use App\Models\ApplicationInterview;
use App\Models\EvaluationCriterion;
use App\Models\InterviewType;
use App\Services\Recruitment\EvaluationService;
use App\Services\Recruitment\InterviewService;
use App\Services\Recruitment\RecruitmentFormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Interviews, panels and the panelist's own scorecard. The interview page is
 * also what a panelist sees: interview details, the applicant's name, the
 * documents submitted with the application, and their own scorecard; never
 * screening, notes, history or other panelists' scorecards.
 */
class InterviewController extends Controller
{
    public function __construct(
        protected InterviewService $interviews,
        protected EvaluationService $evaluations,
        protected RecruitmentFormOptions $options
    ) {}

    public function mine(Request $request): Response
    {
        $filters = $request->only(['scope', 'from', 'to']);

        return Inertia::render('app/Recruitment/Interviews/Mine', [
            'interviews' => $this->interviews->getPaginatedForPanelist($request->user()->employee_id, $filters),
            'filters' => $filters + ['scope' => 'upcoming'],
        ]);
    }

    public function store(ScheduleInterviewRequest $request, Application $application): RedirectResponse
    {
        $interview = $this->interviews->schedule($application, $request->validated(), $request->user());

        return redirect()->route('recruitment.interviews.show', $interview)->with('success', 'Interview scheduled.');
    }

    public function show(Request $request, ApplicationInterview $interview): Response
    {
        $user = $request->user();
        $interview->load([
            'type:id,name',
            'panelists.employee:id,employee_number,emp_first_name,emp_last_name',
            'reschedules.actor:id,username',
            'completer:id,username',
            'canceller:id,username',
            'application:id,application_number,applicant_id,vacancy_id,current_vacancy_stage_id,status',
            'application.applicant:id,first_name,middle_name,last_name',
            'application.vacancy:id,vacancy_number,title',
            'application.currentStage:id,name,stage_type',
            'application.documents' => fn ($q) => $q->with('type:id,name'),
        ]);

        // A cancelled panel no longer grants document access (ApplicantDocumentPolicy); don't list them.
        if ($interview->status === ApplicationInterview::STATUS_CANCELLED && ! $user->can('view', $interview->application)) {
            $interview->application->setRelation('documents', collect());
        }

        $canUpdate = $user->can('update', $interview);
        $canViewEvaluations = $user->can('viewEvaluations', $interview);

        return Inertia::render('app/Recruitment/Interviews/Show', [
            'interview' => $interview,
            'progress' => $this->interviews->evaluationProgress($interview),
            'myScorecard' => $this->evaluations->ownScorecard($interview, $user),
            'scorecards' => $canViewEvaluations
                ? $interview->evaluations()->where('status', ApplicationEvaluation::STATUS_SUBMITTED)
                    ->with(['scores', 'evaluator:id,emp_first_name,emp_last_name'])->orderBy('submitted_at')->get()
                : [],
            'criteria' => EvaluationCriterion::active()->get(['id', 'name', 'description']),
            'ratingScale' => ApplicationEvaluation::RATINGS,
            'interviewTypes' => $canUpdate ? InterviewType::active()->get(['id', 'name']) : [],
            'employees' => $canUpdate ? $this->options->employees() : [],
            'can' => [
                'update' => $canUpdate,
                'evaluate' => $user->can('evaluate', $interview),
                'isPanelist' => $interview->hasPanelist($user->employee_id),
                'viewEvaluations' => $canViewEvaluations,
                'viewApplication' => $user->can('view', $interview->application),
            ],
        ]);
    }

    public function update(UpdateInterviewRequest $request, ApplicationInterview $interview): RedirectResponse
    {
        $this->interviews->update($interview, $request->validated(), $request->user());

        return back()->with('success', 'Interview updated.');
    }

    public function reschedule(RescheduleInterviewRequest $request, ApplicationInterview $interview): RedirectResponse
    {
        $this->interviews->reschedule($interview, $request->validated(), $request->user());

        return back()->with('success', 'Interview rescheduled.');
    }

    public function cancel(CloseInterviewRequest $request, ApplicationInterview $interview): RedirectResponse
    {
        $this->interviews->cancel($interview, $request->validated('reason'), $request->user());

        return back()->with('success', 'Interview cancelled.');
    }

    public function complete(CloseInterviewRequest $request, ApplicationInterview $interview): RedirectResponse
    {
        $this->interviews->complete($interview, $request->validated('remarks'), $request->user());

        return back()->with('success', 'Interview marked completed.');
    }

    public function saveEvaluation(SaveEvaluationRequest $request, ApplicationInterview $interview): RedirectResponse
    {
        $this->evaluations->saveDraft($interview, $request->validated(), $request->user());

        return back()->with('success', 'Scorecard draft saved.');
    }

    public function submitEvaluation(SaveEvaluationRequest $request, ApplicationInterview $interview): RedirectResponse
    {
        $this->evaluations->submit($interview, $request->validated(), $request->user());

        return back()->with('success', 'Scorecard submitted.');
    }
}
