<?php

namespace App\Http\Controllers\Web\People;

use App\Http\Controllers\Controller;
use App\Models\JobTitle;
use App\Services\JobTitleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobTitleController extends Controller
{
    public function __construct(
        protected JobTitleService $jobTitleService
    ) {}

    /**
     * Display a listing of job titles.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search']);

        return Inertia::render('app/People/JobTitles/Index', [
            'jobTitles' => $this->jobTitleService->getPaginatedJobTitles($filters),
            'filters'   => $filters,
        ]);
    }

    /**
     * Store a newly created job title in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'job_title'       => ['required', 'string', 'max:255'],
            'job_description' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->jobTitleService->createJobTitle($validated);

        return redirect()->back()->with('success', 'Job title created successfully.');
    }

    /**
     * Update the specified job title in storage.
     */
    public function update(Request $request, JobTitle $jobTitle): RedirectResponse
    {
        $validated = $request->validate([
            'job_title'       => ['required', 'string', 'max:255'],
            'job_description' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->jobTitleService->updateJobTitle($jobTitle, $validated);

        return redirect()->back()->with('success', 'Job title updated successfully.');
    }

    /**
     * Remove or archive the specified job title.
     */
    public function archive(JobTitle $jobTitle): RedirectResponse
    {
        $this->jobTitleService->archiveJobTitle($jobTitle);

        return redirect()->back()->with('success', 'Job title archived successfully.');
    }
}
