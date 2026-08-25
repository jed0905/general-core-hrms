<?php

namespace App\Http\Controllers\Web\Maintenance;

use Inertia\Inertia;
use App\Models\JobStatus;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateJobStatusRequest;
use App\Http\Requests\CreateJobStatusFormRequest;
use App\Http\Requests\UpdateJobStatusFormRequest;

class MaintenanceJobStatusesController extends Controller
{
    public function index()
    {
        $jobStatuses = JobStatus::get();
        return Inertia::render('app/Maintenance/JobStatuses/Index', [
            'jobStatuses' => $jobStatuses,
        ]);
    }

    public function create()
    {
        return Inertia::render('app/Maintenance/JobStatuses/Create');
    }

    public function store(CreateJobStatusFormRequest $request)
    {
        try {
            JobStatus::create($request->validated());
            return redirect()->route('maintenance.job-status.create');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function edit(string $id)
    {
        $jobStatus = JobStatus::where('id', $id)->first();
        return Inertia::render('app/Maintenance/JobStatuses/Edit', [
            'jobStatus' => $jobStatus,
        ]);
    }

    public function update(UpdateJobStatusFormRequest $request, string $id)
    {

        try {
            JobStatus::where('id', $id)->update($request->validated());
            return redirect()->route('maintenance.job-statuses.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function destroy(string $id)
    {
        try {
            JobStatus::where('id', $id)->delete();
            return redirect()->route('maintenance.job-statuses.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }
}
