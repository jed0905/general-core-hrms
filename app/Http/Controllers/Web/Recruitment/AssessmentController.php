<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\CancelAssessmentRequest;
use App\Http\Requests\Recruitment\CompleteAssessmentRequest;
use App\Http\Requests\Recruitment\StoreAssessmentRequest;
use App\Http\Requests\Recruitment\UpdateAssessmentRequest;
use App\Models\Application;
use App\Models\ApplicationAssessment;
use App\Services\Recruitment\AssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Assessments are managed from the application page.
 */
class AssessmentController extends Controller
{
    public function __construct(protected AssessmentService $assessments) {}

    public function store(StoreAssessmentRequest $request, Application $application): RedirectResponse
    {
        $this->assessments->create($application, $request->validated(), $request->user());

        return back()->with('success', 'Assessment added.');
    }

    public function update(UpdateAssessmentRequest $request, ApplicationAssessment $assessment): RedirectResponse
    {
        $this->assessments->update($assessment, $request->validated(), $request->user());

        return back()->with('success', 'Assessment updated.');
    }

    public function start(Request $request, ApplicationAssessment $assessment): RedirectResponse
    {
        $this->assessments->start($assessment, $request->user());

        return back()->with('success', 'Assessment started.');
    }

    public function complete(CompleteAssessmentRequest $request, ApplicationAssessment $assessment): RedirectResponse
    {
        $this->assessments->complete($assessment, $request->validated(), $request->user());

        return back()->with('success', 'Assessment completed.');
    }

    public function cancel(CancelAssessmentRequest $request, ApplicationAssessment $assessment): RedirectResponse
    {
        $this->assessments->cancel($assessment, $request->validated('reason'), $request->user());

        return back()->with('success', 'Assessment cancelled.');
    }
}
