<?php

namespace App\Http\Controllers\Web\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\CompleteOnboardingTaskRequest;
use App\Http\Requests\People\OnboardingReasonRequest;
use App\Models\OnboardingTask;
use App\Services\Onboarding\OnboardingTaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Task actions, shared by HR, self-service and assigned supervisors; the
 * policy decides who may act on which task.
 */
class OnboardingTaskController extends Controller
{
    public function __construct(protected OnboardingTaskService $tasks) {}

    public function start(Request $request, OnboardingTask $onboardingTask): RedirectResponse
    {
        $this->tasks->start($onboardingTask, $request->user());

        return back()->with('success', 'Task started.');
    }

    public function complete(CompleteOnboardingTaskRequest $request, OnboardingTask $onboardingTask): RedirectResponse
    {
        $this->tasks->complete($onboardingTask, $request->safe()->only(['remarks', 'employee_document_id']) + ['file' => $request->file('file')], $request->user());

        return back()->with('success', 'Task completed.');
    }

    public function verify(Request $request, OnboardingTask $onboardingTask): RedirectResponse
    {
        $this->tasks->verify($onboardingTask, $request->user());

        return back()->with('success', 'Task verified.');
    }

    public function skip(OnboardingReasonRequest $request, OnboardingTask $onboardingTask): RedirectResponse
    {
        $this->tasks->skip($onboardingTask, $request->validated('reason'), $request->user());

        return back()->with('success', 'Task skipped.');
    }
}
